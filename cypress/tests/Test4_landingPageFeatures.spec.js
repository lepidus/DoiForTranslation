describe('Author Version - Landing page features', function () {
    it('List of translations of a submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#active-button').click();
        cy.get('.pkpButton:visible:contains("View")').eq(1).click();

        cy.get('.pkpHeader__actions a:contains("View")').click();
        
        cy.contains('Translations of this article');
        cy.contains('Français (Canada) - Testing plugin for creating translation of submissions');
    });
});