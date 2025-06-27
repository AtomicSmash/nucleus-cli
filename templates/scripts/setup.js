#!/usr/bin/env node
import { exec } from "node:child_process";
import { constants, copyFile } from "node:fs/promises";
import { performance } from "node:perf_hooks";
import { promisify } from "node:util";

const execute = promisify(exec);
const theme_name = process.env.npm_package_config_theme_name;
const installAndBuildOnly = await import("dotenv")
	.then((dotenv) => {
		dotenv.config();
		return process.env.SETUP_INSTALL_AND_BUILD_ONLY.toLowerCase() === "true";
	})
	.catch(() => {
		return false;
	});

// @ts-ignore
function convertMeasureToPrettyString(measure) {
	const duration = Number(measure.duration);
	if (duration < 1) {
		return `${duration * 1000}µs`;
	}
	if (duration < 999) {
		return `${Math.round(duration)}ms`;
	}
	const time_in_seconds = Number((duration / 1000).toFixed(2));
	if (time_in_seconds < 60) {
		return `${time_in_seconds}s`;
	}
	const minutes = Math.floor(time_in_seconds / 60);
	const seconds = Math.ceil(time_in_seconds % 60);
	return `${minutes}m ${seconds}s`;
}

if (!theme_name) {
	if (!theme_name) {
		console.error("Theme name is missing from package.json config object.");
	}
	process.exitCode = 1;
} else {
	let $i = 3;
	process.stdout.write(`Running setup${".".repeat($i)}\r`);
	$i++;
	let interval = null;
	if (typeof process.stdout.clearLine !== "undefined") {
		interval = setInterval(() => {
			if ($i > 2) {
				$i = 0;
			}
			$i++;
			process.stdout.clearLine(0);
			process.stdout.cursorTo(0);
			process.stdout.write(`Running setup${".".repeat($i)}\r`);
		}, 200);
	}
	performance.mark("Start");
	await Promise.allSettled([
		...(installAndBuildOnly
			? []
			: [
					copyFile(".env.example", ".env", constants.COPYFILE_EXCL)
						.then(() => {
							console.log(
								`.env.example file copied to .env. (${convertMeasureToPrettyString(
									performance.measure("Copy env file", "Start"),
								)})`,
							);
						})
						.catch((error) => {
							if (error.name) {
								console.log(
									`Didn't copy .env file because one already exists. (${convertMeasureToPrettyString(
										performance.measure("Copy env file", "Start"),
									)})`,
								);
								return;
							}
							console.error(error);
							throw new Error("Failed to copy .env file.");
						}),
					execute(`valet link ${theme_name} --secure --isolate`)
						.then(() => {
							console.log(
								`Valet is linked, secured and isolated. (${convertMeasureToPrettyString(
									performance.measure("valet", "Start"),
								)})`,
							);
						})
						.catch((error) => {
							console.error(error);
							throw new Error("Failed to link the site using valet.");
						}),
				]),
		execute("composer install")
			.then(() => {
				console.log(
					`Root composer install done. (${convertMeasureToPrettyString(
						performance.measure("root composer install", "Start"),
					)})`,
				);
			})
			.catch((error) => {
				console.error(error);
				throw new Error(
					"Failed to run composer install in the root directory.",
				);
			}),
		(async () => {
			await execute("npm install")
				.then(() => {
					performance.mark("root npm install done");
					console.log(
						`Root npm install done. (${convertMeasureToPrettyString(
							performance.measure("root npm install", "Start"),
						)})`,
					);
				})
				.catch((error) => {
					console.error(error);
					throw new Error("Failed to run npm install in the root directory.");
				});
			await execute("npm run build")
				.then(() => {
					console.log(
						`Initial build done. (${convertMeasureToPrettyString(
							performance.measure("build", "root npm install done"),
						)})`,
					);
				})
				.catch((error) => {
					console.error(error);
					throw new Error("Failed to run a build after installing.");
				});
            await execute("npx changeset init")
                .then(() => {
                    performance.mark("changeset init done");
                    console.log(
                        `Changeset init done. (${convertMeasureToPrettyString(
                            performance.measure("changeset init", "root npm install done"),
                        )})`,
                    );
                })
                .catch((error) => {
                    console.error(error);
                    throw new Error("Failed to run changeset init.");
                });
		})().catch((reason) => {
			throw new Error(reason);
		}),
	]).then((results) => {
		if (interval !== null) {
			clearInterval(interval);
		}
		if (results.some((result) => result.status === "rejected")) {
			process.exitCode = 1;
			console.error("Setup failed with the following errors:\n");
			console.error(
				results
					.filter((result) => result.status === "rejected")
					.map((result) => {
						return `- ${result.reason}`;
					})
					.join(`\n`),
			);
		} else {
			console.log(
				`Setup is complete. ${convertMeasureToPrettyString(
					performance.measure("everything", "Start"),
				)}`,
			);
		}
	});
}
