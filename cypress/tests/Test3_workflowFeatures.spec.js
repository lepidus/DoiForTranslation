describe('Submissions Translation - Workflow features', function () {
    it('List of translations of a submission', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#active-button').click();
        cy.get('.pkpButton:visible:contains("View")').eq(1).click();

        cy.contains('Translations').click();
        cy.get('.pkpPublication__translations a:contains("Français (Canada)")');
        cy.get('.pkpPublication__translations li a').should('have.length', 1);
        cy.get('.pkpPublication__translations a:contains("Français (Canada)")').click();

        cy.contains('Plugin de test pour créer une traduction de soumission');
    });
    it('Reference to translated submission on translation submission workflow', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#active-button').click();
        cy.get('.pkpButton:visible:contains("View")').first().click();

        cy.contains('Translated submission').click();
        
        cy.contains('Testing plugin for creating translation of submissions');
    });
});