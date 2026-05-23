<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create numbers table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<SQL
            CREATE TYPE number_status AS ENUM ('active', 'blocked', 'archived')
        SQL);

        $this->addSql(<<<SQL
            CREATE TABLE numbers (
                id UUID NOT NULL DEFAULT gen_random_uuid(),
                number VARCHAR(15) NOT NULL,
                status number_status NOT NULL DEFAULT 'active',
                tariff VARCHAR(100) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_numbers_number ON numbers (number)');
        $this->addSql('CREATE INDEX IDX_numbers_status ON numbers (status)');
        $this->addSql('CREATE INDEX IDX_numbers_tariff ON numbers (tariff)');
        $this->addSql('CREATE INDEX IDX_numbers_created_at ON numbers (created_at)');
        $this->addSql('CREATE INDEX IDX_numbers_updated_at ON numbers (updated_at)');

        $this->addSql(<<<SQL
            COMMENT ON COLUMN numbers.id IS '(DC2Type:uuid)'
        SQL);
        $this->addSql(<<<SQL
            COMMENT ON COLUMN numbers.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<SQL
            COMMENT ON COLUMN numbers.updated_at IS '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE numbers');
        $this->addSql('DROP TYPE number_status');
    }
}
