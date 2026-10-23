<?php

use APP\plugins\generic\doiForTranslation\DoiForTranslationPlugin;
use PHPUnit\Framework\TestCase;

class SubmissionSchemaTest extends TestCase
{
    public function testIsTranslationOfCannotBeWrittenThroughTheApi(): void
    {
        $schema = (object) ['properties' => new stdClass()];

        (new DoiForTranslationPlugin())->addOurFieldsToSubmissionSchema('Schema::get::submission', [&$schema]);

        $this->assertSame('integer', $schema->properties->isTranslationOf->type);
        $this->assertTrue($schema->properties->isTranslationOf->writeDisabledInApi ?? false);
    }
}
