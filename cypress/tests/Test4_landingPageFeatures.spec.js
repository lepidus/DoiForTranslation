import '../support/commands.js';

function publishSubmission() {
    cy.recordEditorialDecision('Accept and Skip Review');
    cy.get('li.ui-state-active a:contains("Copyediting")');
    cy.get('#publication-button').click();
    cy.get('button:contains("Schedule For Publication")').click();
    cy.waitJQuery();
    
    cy.get('#assignToIssue-issueId-control').select('1');
    cy.get('div[id^="assign-"] button:contains("Save")').click();
    cy.waitJQuery();
    
    cy.get('button:contains("Publish")').click();
}

function assignMyselfAsJournalEditor() {
    cy.get('a:contains("Assign")').click();
    cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
    cy.get("#addParticipantForm > .formButtons > .submitFormButton").click();
    cy.waitJQuery();
}

describe('Submissions Translation - Landing page features', function () {
    let title;

    before(function() {
        title =  {
            'en_US': 'Testing plugin for creating translation of submissions',
            'fr_CA': 'Plugin de test pour créer une traduction de soumission'
        }
    });
    
    it('Editor publishes both submissions', function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', title['en_US']);
        publishSubmission();

        cy.get('a:contains("Submissions")').click();
        cy.findSubmission('active', title['fr_CA']);
        assignMyselfAsJournalEditor();
        publishSubmission();
    });
    it('List of translations of a submission in landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', title['en_US']);

        cy.get('.pkpHeader__actions a:contains("View")').click();
        
        cy.contains('h1', title['en_US']);
        cy.scrollTo('bottom');
        cy.contains('Translations of this article');
        cy.contains('a', title['fr_CA']).click();

        cy.contains('h1', title['fr_CA']);
    });
    it('Reference to translated submission on translation submission landing page', function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('archive', title['fr_CA']);

        cy.get('.pkpHeader__actions a:contains("View")').click();
        
        cy.contains('h1', title['fr_CA']);
        cy.scrollTo('bottom');
        cy.contains('h2', 'Translation');
        cy.contains('This article is a translation of the article:');
        cy.contains('a', title['en_US']).click();

        cy.contains('h1', title['en_US']);
    });
});