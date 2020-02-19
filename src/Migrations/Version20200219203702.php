<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200219203702 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE test_session_item (id INT AUTO_INCREMENT NOT NULL, test_session_id INT NOT NULL, question VARCHAR(255) NOT NULL, answers JSON NOT NULL, category VARCHAR(255) NOT NULL, level INT NOT NULL, result VARCHAR(255) DEFAULT NULL, INDEX IDX_F5ECC5041A0C5AE6 (test_session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE test_session_item ADD CONSTRAINT FK_F5ECC5041A0C5AE6 FOREIGN KEY (test_session_id) REFERENCES test_session (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE test_session_item');
    }
}
