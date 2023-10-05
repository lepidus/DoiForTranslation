describe('Author Version - Workflow features', function () {
    it('List of translations of a submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#active-button').click();
        cy.get('.pkpButton:visible:contains("View")').eq(1).click();

        cy.contains('Translations').click();
        cy.get('.pkpPublication__translations a:contains("Français (Canada)")');
        cy.get('.pkpPublication__translations li a').should('have.length', 1);
    });
});