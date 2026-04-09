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

// Cron schedule: */3 * * * * (every 3 minutes)

async function checkViolationsAndCreateChallans() {
  const conn = await pool.getConnection();

  try {
    // Step 1: Get all active violations where pollution_value exceeds threshold
    const [violations] = await conn.query<any[]>(
      `SELECT v.id, v.vehicle_id, v.pollution_value, v.violation_count, v.minutes_count,
              ve.vehicle_number, ve.owner_email
       FROM violations v
       JOIN vehicles ve ON v.vehicle_id = ve.id
       WHERE v.pollution_value > 100`
    );

    console.log(`Found ${violations.length} vehicles with pollution_value > 100`);

    let challansCreated = 0;

    for (const violation of violations) {
      const { vehicle_id, pollution_value, violation_count, minutes_count, vehicle_number } =
        violation;

      // Step 2: Increment minutes_count by 1 for this 3-minute cycle
      const newMinutesCount = minutes_count + 1;

      console.log(
        `Vehicle ${vehicle_number} (ID: ${vehicle_id}): pollution=${pollution_value}, minutes_count=${minutes_count} → ${newMinutesCount}`
      );

      if (newMinutesCount >= 3) {
        // Step 3a: Pollution has been high for 3+ consecutive cycles — create a challan

        // Check previous unpaid challans to scale the amount
        const [previousChallans] = await conn.query<any[]>(
          `SELECT COUNT(*) as count FROM challans WHERE vehicle_id = ? AND status = 'unpaid'`,
          [vehicle_id]
        );

        const prevCount = previousChallans[0]?.count || 0;
        const baseAmount = 500;
        const additionalAmount = prevCount * 250;
        const totalAmount = baseAmount + additionalAmount;
        const challanCount = 1 + prevCount;

        console.log(
          `Vehicle ${vehicle_number}: ${prevCount} previous unpaid challans, count=${challanCount}, amount=₹${totalAmount}`
        );

        // Insert the challan
        await conn.query(
          `INSERT INTO challans (vehicle_id, amount, status, violation_count, count, challan_date, updated_at)
           VALUES (?, ?, 'unpaid', ?, ?, NOW(), NOW())`,
          [vehicle_id, totalAmount, violation_count, challanCount]
        );

        console.log(
          `✓ Challan created for ${vehicle_number} (ID: ${vehicle_id}) - Count: ${challanCount}, Amount: ₹${totalAmount}`
        );

        // Step 3b: Reset minutes_count to 0 after challan creation
        await conn.query(
          `UPDATE violations SET minutes_count = 0, violation_date = NOW() WHERE vehicle_id = ?`,
          [vehicle_id]
        );

        console.log(`✓ minutes_count reset to 0 for ${vehicle_number}`);
        challansCreated++;
      } else {
        // Step 3c: Not yet at threshold — persist the incremented minutes_count
        await conn.query(
          `UPDATE violations SET minutes_count = ?, violation_date = NOW() WHERE vehicle_id = ?`,
          [newMinutesCount, vehicle_id]
        );

        console.log(
          `↑ minutes_count updated to ${newMinutesCount} for ${vehicle_number} (challan at 3)`
        );
      }
    }

    return {
      status: "success",
      challansCreated,
      message: `Processed ${violations.length} violations. ${challansCreated} challan(s) created.`,
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

// Run the check (scheduled every 3 minutes: */3 * * * *)
const result = await checkViolationsAndCreateChallans();
console.log(JSON.stringify(result, null, 2));
