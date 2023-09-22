describe('Submissions Translation - Plugin setup', function () {
    it('Enables Submissions Translation plugin', function () {
		cy.login('dbarnes', null, 'publicknowledge');

		cy.get('a:contains("Website")').click();

		cy.waitJQuery();
		cy.get('button#plugins-button').click();

		cy.get('input[id^=select-cell-submissionstranslationplugin]').check();
		cy.get('input[id^=select-cell-submissionstranslationplugin]').should('be.checked');
    });
});