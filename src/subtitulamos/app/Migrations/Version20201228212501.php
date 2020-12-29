<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201228212501 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE shows DROP zero_tolerance');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE shows ADD zero_tolerance tinyint(1) DEFAULT 0 NOT NULL');
    }
}
