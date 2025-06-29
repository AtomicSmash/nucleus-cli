import { exec } from "node:child_process";
import { promisify } from "node:util";
import "dotenv/config";

const execute = promisify(exec);

const addCustomUser =
	process.env.WORDPRESS_USER &&
	process.env.WORDPRESS_USER_EMAIL &&
	process.env.WORDPRESS_PASSWORD;

console.log("Creating database...");
await execute("wp db create")
	.then(() => {
		console.log("Installing wordpress database tables...");
		return execute(
			`wp core install --url=http://${process.env.CI ? "127.0.0.1" : "nucleus.test"}/ --title=Temp --admin_user=Bot --admin_email=fake@fake.com --admin_password=password`,
		);
	})
	.then(() => {
		if (addCustomUser) {
			console.log("Adding user from .env file...");
			return execute(
				`wp user create ${process.env.WORDPRESS_USER} ${process.env.WORDPRESS_USER_EMAIL} --user_pass=${process.env.WORDPRESS_PASSWORD} --role=administrator`,
			);
		}
	})
	.then(() => {
		console.log("Activating plugins...");
		return execute(
			`wp plugin activate --all --exclude=wordfence,shortpixel-image-optimiser`,
		);
	})
	.then(() => {
		console.log("Activating theme...");
		return execute(`wp theme activate {{THEME_NAME}}`);
	})
	.then(() => {
		console.log(
			`Database set up${addCustomUser ? ` and ${process.env.WORDPRESS_USER} user added` : !process.env.CI ? ". To set up a user, run the `wp user create` command." : ""}.`,
		);
	})
	.catch((error) => {
		if (error.stderr.startsWith("ERROR 1007")) {
			console.error(
				"Database already exists with the name in the wp-config. Please delete that database first with `wp db drop --yes`",
			);
		} else {
			console.error(error);
		}
	});
