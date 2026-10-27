Cypress.Commands.add('openSubmissionInLocale', function(title, locale) {
    cy.request('index.php/publicknowledge/api/v1/submissions?searchPhrase=' + encodeURIComponent(title)).then(response => {
        const submissions = response.body.items.filter(item => item.locale === locale);
        expect(submissions, 'submission "' + title + '" in ' + locale).to.have.length(1);
        cy.visit(submissions[0].urlWorkflow);
    });
});
