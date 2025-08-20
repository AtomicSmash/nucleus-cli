import { exec } from "node:child_process";
import { promises as fs } from "node:fs";
import { promisify } from "node:util";
import { resolve } from "node:path";
import "dotenv/config";

const execute = promisify(exec);

const staging_ssh_username = process.env.STAGING_SSH_USERNAME;
const staging_ssh_host = process.env.STAGING_SSH_HOST;
const staging_ssh_port = process.env.STAGING_SSH_PORT;
const media_months = parseInt(process.env.MEDIA_DOWNLOAD_MONTHS || "-1");
const media_path = process.env.MEDIA_DOWNLOAD_PATH;
const media_local_path = process.env.MEDIA_DOWNLOAD_LOCAL_PATH;

async function getDateForMonthsAgo(monthsAgo) {
	const date = new Date();
	date.setMonth(date.getMonth() - monthsAgo);
	return {
		year: date.getFullYear(),
		month: String(date.getMonth() + 1).padStart(2, "0"),
	};
}

async function downloadFiles(remote_path, local_path) {
	try {
		// Create local directory if it doesn't exist
		await fs.mkdir(local_path, { recursive: true });

		// Download files using scp
		await execute(
			`scp -r -p -O -P ${staging_ssh_port} "${staging_ssh_username}@${staging_ssh_host}:${remote_path}" "${local_path}"`,
		);
	} catch (error) {
		console.log(`Failed to download ${remote_path}`);
		if (error.stderr) {
			console.error(error.stderr);
		}
		throw new Error("Error downloading media.");
	}
}

async function downloadFullUploads() {
	const remote_path = media_path;
	const local_path = resolve(media_local_path, "..");

	console.log(
		`Downloading entire uploads directory: ${remote_path} -> ${local_path}`,
	);
	await downloadFiles(remote_path, local_path);
}

async function downloadMediaForMonth(year, month) {
	const remote_path = `${media_path}/${year}/${month}`;
	const local_path = `${media_local_path}/${year}`;

	console.log(`Attempting to download: ${remote_path} -> ${local_path}`);
	try {
		await downloadFiles(remote_path, local_path);
	} catch (error) {
		console.log(
			`Skipping uploads/${year}/${month} - directory does not exist on remote server`,
		);
		return; // Skip to next iteration
	}
}

async function downloadMedia() {
	if (media_months === -1) {
		console.log("Downloading entire uploads directory...");
		await downloadFullUploads();
	} else {
		console.log(`Downloading media for the last ${media_months} months...`);
		// Download media for each month
		for (let i = 0; i < media_months; i++) {
			const { year, month } = await getDateForMonthsAgo(i);
			await downloadMediaForMonth(year, month);
		}
		console.log("Finished attempting to download all requested months");
	}
}

await downloadMedia()
	.then(() => {
		console.log("Media download complete!");
	})
	.catch(() => {
		console.log(
			"There was an error downloading the media, see the message above.",
		);
	});
