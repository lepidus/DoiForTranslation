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

	workflowStore.extender.extendFn('getHeaderItems', (headerItems, args) => [
		...headerItems,
		{
			component: 'DoiForTranslationWorkflowActions',
			props: {submission: args.submission},
		},
	]);
});
