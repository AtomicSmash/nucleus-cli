import { exec } from "node:child_process";
import { createReadStream, createWriteStream, promises as fs } from "node:fs";
import { promisify } from "node:util";
import readline from "node:readline";
import "dotenv/config";

const execute = promisify(exec);

const theme_name = process.env.npm_package_config_theme_name;

const staging_ssh_username = process.env.STAGING_SSH_USERNAME;
const staging_ssh_host = process.env.STAGING_SSH_HOST;
const staging_ssh_port = process.env.STAGING_SSH_PORT;
let staging_url = process.env.STAGING_URL;

if (!staging_url) {
	console.error("STAGING_URL is missing from .env file.");
	process.exitCode = 1;
} else {
	if (staging_url.endsWith("/")) {
		staging_url = staging_url.slice(0, -1);
	}

	const tmpFile = "/tmp/nucleus.sql";
	const tmpFileProcessed = "/tmp/nucleus.processed.sql";

	console.log("Pulling database from staging...");
	try {
		await execute(
			`ssh ${staging_ssh_username}@${staging_ssh_host} -p ${staging_ssh_port} "cd {{WEB_ROOT}}current && wp db export - --add-drop-table" > ${tmpFile}`,
		);

		console.log("Updating URLs in database SQL (streaming)...");
		await new Promise((resolve, reject) => {
			const readStream = createReadStream(tmpFile, { encoding: "utf8" });
			const writeStream = createWriteStream(tmpFileProcessed, {
				encoding: "utf8",
			});

			const rl = readline.createInterface({ input: readStream });

			rl.on("line", (line) => {
				writeStream.write(
					line.replaceAll(staging_url, `//${theme_name}.test`) + "\n",
				);
			});

			rl.on("close", () => {
				writeStream.end();
			});

			writeStream.on("finish", resolve);
			writeStream.on("error", reject);
			readStream.on("error", reject);
		});

		console.log("Importing database...");
		await execute(`wp db query < ${tmpFileProcessed}`);

		await fs.unlink(tmpFile);
		await fs.unlink(tmpFileProcessed);

		console.log("\nDatabase import complete!");
		console.log(
			"If you want to download recent media you can use `npm run pull:media`, you can also change the number of months to download in your `.env` file.",
		);
	} catch (error) {
		console.error("Error during database pull:", error);

		const cleanupResults = await Promise.allSettled([
			fs.unlink(tmpFile),
			fs.unlink(tmpFileProcessed),
		]);

		const failedCleanups = cleanupResults.filter(
			(result) => result.status === "rejected",
		);
		if (failedCleanups.length > 0) {
			console.warn(
				`Warning: Failed to delete ${failedCleanups.length} temporary file(s). You may need to clean them up manually.`,
			);
		}
	}
}
