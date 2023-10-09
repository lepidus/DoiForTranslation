function publishSubmission() {
    cy.recordEditorialDecision('Accept and Skip Review');
    cy.get('li.ui-state-active a:contains("Copyediting")');
    cy.get('#publication-button').click();
    cy.get('button:contains("Schedule For Publication")').click();
    cy.waitJQuery();
    
    cy.get('#assignToIssue-issueId-control').select('1');
    cy.get('div[id^="assign-"] button:contains("Save")').click();
    cy.waitJQuery();
}

describe('Author Version - Landing page features', function () {
    it('Editor publishes both submissions', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#active-button').click();
        cy.get('.pkpButton:visible:contains("View")').eq(1).click();
        publishSubmission();

        cy.get('a:contains("Submissions")').click();
        cy.get('#active-button').click();
        cy.get('.pkpButton:visible:contains("View")').first().click();
        publishSubmission();
    });
    it('List of translations of a submission in landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#active-button').click();
        cy.get('.pkpButton:visible:contains("View")').eq(1).click();

        cy.get('.pkpHeader__actions a:contains("View")').click();
        
        cy.contains('Translations of this article');
        cy.contains('Français (Canada) - Plugin de test pour créer une traduction de soumission');
    });
});