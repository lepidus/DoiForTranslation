import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

function headerItems(roles, assignedRoles) {
	const source = readFileSync(
		new URL('../resources/js/main.js', import.meta.url),
		'utf8',
	).replace(/^import .*;$/m, '');
	let extend;
	const pkp = {
		currentUser: {roles},
		const: {ROLE_ID_SITE_ADMIN: 1, ROLE_ID_MANAGER: 16, ROLE_ID_SUB_EDITOR: 17},
		registry: {
			registerComponent() {},
			storeExtend(name, callback) {
				callback({
					store: {
						dashboardPage: 'editorialDashboard',
						extender: {
							extendFn(name, fn) {
								extend = fn;
							},
						},
					},
				});
			},
		},
	};
	runInNewContext(source, {pkp, DoiForTranslationWorkflowActions: {}});
	return extend(['existing'], {
		submission: {id: 1, stages: [{currentUserAssignedRoles: assignedRoles}]},
	});
}

test('manager and assigned section editor get translation actions', () => {
	assert.equal(headerItems([16], []).length, 2);
	assert.equal(headerItems([1], []).length, 2);
	assert.equal(headerItems([17], [17]).length, 2);
});
test('copyeditor, layout editor and unassigned section editor do not fetch translation actions', () => {
	assert.equal(headerItems([4097], [4097]).length, 1);
	assert.equal(headerItems([17], []).length, 1);
});
