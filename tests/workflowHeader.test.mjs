import {test} from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {runInNewContext} from 'node:vm';

function headerItems(
	roles,
	assignedRoles,
	activeStage = {},
	otherStages = [],
	submissionProgress = '',
) {
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
		submission: {
			id: 1,
			submissionProgress,
			stages: [
				{
					isActiveStage: true,
					currentUserAssignedRoles: assignedRoles,
					...activeStage,
				},
				...otherStages,
			],
		},
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
test('translation actions offer creation to assigned deciding editors', () => {
	assert.equal(headerItems([16], [16])[1].props.canCreateTranslation, true);
	assert.equal(headerItems([1], [1])[1].props.canCreateTranslation, true);
	assert.equal(headerItems([17], [17])[1].props.canCreateTranslation, true);
});
test('recommend only editors see translations but cannot create them', () => {
	const items = headerItems([17], [17], {currentUserCanRecommendOnly: true});
	assert.equal(items.length, 2);
	assert.equal(items[1].props.canCreateTranslation, false);
});
test('section editor without an editorial role in the active stage cannot create translations', () => {
	const items = headerItems([17], [65536], {}, [
		{isActiveStage: false, currentUserAssignedRoles: [17]},
	]);
	assert.equal(items.length, 2);
	assert.equal(items[1].props.canCreateTranslation, false);
});
test('incomplete submissions do not offer translation creation', () => {
	const items = headerItems([16], [16], {}, [], 'details');
	assert.equal(items[1].props.canCreateTranslation, false);
});
