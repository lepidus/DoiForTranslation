import DoiForTranslationWorkflowActions from './components/DoiForTranslationWorkflowActions.vue';

pkp.registry.registerComponent(
	'DoiForTranslationWorkflowActions',
	DoiForTranslationWorkflowActions,
);

pkp.registry.storeExtend('workflow', (piniaContext) => {
	const workflowStore = piniaContext.store;

	if (workflowStore.dashboardPage !== 'editorialDashboard') {
		return;
	}

	workflowStore.extender.extendFn('getHeaderItems', (headerItems, args) => {
		const roles = pkp.currentUser.roles;
		const isManager = roles.some((role) =>
			[pkp.const.ROLE_ID_SITE_ADMIN, pkp.const.ROLE_ID_MANAGER].includes(role),
		);
		const isAssignedEditor =
			roles.includes(pkp.const.ROLE_ID_SUB_EDITOR) &&
			args.submission.stages.some((stage) =>
				stage.currentUserAssignedRoles.includes(pkp.const.ROLE_ID_SUB_EDITOR),
			);
		if (!isManager && !isAssignedEditor) {
			return headerItems;
		}
		const activeStage = args.submission.stages.find(
			(stage) => stage.isActiveStage,
		);
		const canCreateTranslation =
			!args.submission.submissionProgress &&
			!activeStage.currentUserCanRecommendOnly &&
			activeStage.currentUserAssignedRoles.some((role) =>
				[
					pkp.const.ROLE_ID_SITE_ADMIN,
					pkp.const.ROLE_ID_MANAGER,
					pkp.const.ROLE_ID_SUB_EDITOR,
				].includes(role),
			);
		return [
			...headerItems,
			{
				component: 'DoiForTranslationWorkflowActions',
				props: {submission: args.submission, canCreateTranslation},
			},
		];
	});
});
