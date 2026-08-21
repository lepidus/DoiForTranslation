import fs from 'fs';
import path from 'path';

const uniqueKeys = new Set();

export default function i18nExtractKeys() {
	const fileOutput = path.join('registry', 'uiLocaleKeysBackend.json');
	const regex = /(?:^|\W)tk?\(\s*['"`](?<localeKey>[^'"`]+)['"`]/g;

	return {
		name: 'extract-i18n-keys',
		transform(code, id) {
			if (
				!id.includes('node_modules') &&
				(id.endsWith('.vue') || id.endsWith('.js'))
			) {
				for (const match of code.matchAll(regex)) {
					uniqueKeys.add(match.groups.localeKey);
				}
			}

			return null;
		},
		buildEnd() {
			if (!uniqueKeys.size) {
				return;
			}

			fs.mkdirSync(path.dirname(fileOutput), {recursive: true});
			fs.writeFileSync(
				fileOutput,
				`${JSON.stringify([...uniqueKeys].sort(), null, 2)}\n`,
			);
		},
	};
}
