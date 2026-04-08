import mysql from "mysql2/promise";

const pool = mysql.createPool({
  host: Bun.env.DB_HOST,
  user: Bun.env.DB_USER,
  password: Bun.env.DB_PASSWORD,
  database: Bun.env.DB_NAME,
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
});

async function checkViolationsAndCreateChallans() {
  const conn = await pool.getConnection();

  try {
    // Step 1: Get all violations with count >= 3
    const [violations] = await conn.query<any[]>(
      `SELECT v.id, v.vehicle_id, v.violation_count, ve.vehicle_number, ve.owner_email
       FROM violations v 
       JOIN vehicles ve ON v.vehicle_id = ve.id 
       WHERE v.violation_count >= 3`
    );

    console.log(`Found ${violations.length} vehicles with violations >= 3`);

    for (const violation of violations) {
      const { vehicle_id, violation_count, vehicle_number, owner_email } =
        violation;

      // Check for previous unpaid challans to calculate count and amount
      const [previousChallans] = await conn.query<any[]>(
        `SELECT COUNT(*) as count FROM challans WHERE vehicle_id = ? AND status = 'unpaid'`,
        [vehicle_id]
      );

      const prevCount = previousChallans[0]?.count || 0;
      const baseAmount = 500;
      const additionalAmount = prevCount * 250;
      const totalAmount = baseAmount + additionalAmount;
      
      // count = 1 (base) + number of previous unpaid challans
      const challanCount = 1 + prevCount;

      console.log(
        `Vehicle ${vehicle_number}: ${prevCount} previous unpaid challans, count=${challanCount}, amount=₹${totalAmount}`
      );

      // Step 2: INSERT new challan record
      const [insertResult] = await conn.query(
        `INSERT INTO challans (vehicle_id, amount, status, violation_count, count, challan_date, updated_at)
         VALUES (?, ?, 'unpaid', ?, ?, NOW(), NOW())`,
        [vehicle_id, totalAmount, violation_count, challanCount]
      );

      console.log(
        `✓ Challan created for ${vehicle_number} (ID: ${vehicle_id}) - Count: ${challanCount}, Amount: ₹${totalAmount}`
      );

      // Step 3: Clear violation count to 0
      await conn.query(
        `UPDATE violations SET violation_count = 0, violation_date = NOW() WHERE vehicle_id = ?`,
        [vehicle_id]
      );

      console.log(`✓ Violation count cleared for ${vehicle_number}`);
    }

    // Step 4: Clear violation counts for vehicles with count < 3
    await conn.query(
      `UPDATE violations SET violation_count = 0, violation_date = NOW() 
       WHERE violation_count > 0 AND violation_count < 3`
    );

    console.log(`✓ Cleared violations for vehicles with count < 3`);

    return {
      status: "success",
      challansCreated: violations.length,
      message: `Processed ${violations.length} violations. Challans created and violation counts cleared.`,
    };
  } catch (error) {
    console.error("Error in violation checker:", error);
    return {
      status: "error",
      message: error instanceof Error ? error.message : "Unknown error",
    };
  } finally {
    await conn.release();
  }
}

// Run the check
const result = await checkViolationsAndCreateChallans();
console.log(JSON.stringify(result, null, 2));
