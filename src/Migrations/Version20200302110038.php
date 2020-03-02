<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200302110038 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_session_template_item (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, test_session_template_id INT NOT NULL, level VARCHAR(255) NOT NULL, cutoff INT NOT NULL, INDEX IDX_66EDDE4E12469DE2 (category_id), INDEX IDX_66EDDE4E93E6FAAB (test_session_template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE question (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, name LONGTEXT NOT NULL, level INT NOT NULL, question_type VARCHAR(255) NOT NULL, answer_uid_type VARCHAR(255) NOT NULL, INDEX IDX_B6F7494E12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, first_name VARCHAR(255) DEFAULT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_session (id INT AUTO_INCREMENT NOT NULL, test_session_template_id INT NOT NULL, email VARCHAR(255) NOT NULL, time_limit INT NOT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, uuid VARCHAR(255) NOT NULL, questions_count INT NOT NULL, result DOUBLE PRECISION DEFAULT NULL, cutoff_success DOUBLE PRECISION DEFAULT NULL, test_session_url VARCHAR(255) DEFAULT NULL, INDEX IDX_C05011C93E6FAAB (test_session_template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_session_item (id INT AUTO_INCREMENT NOT NULL, test_session_id INT NOT NULL, question LONGTEXT NOT NULL, answers LONGTEXT NOT NULL, category VARCHAR(255) NOT NULL, level INT NOT NULL, result INT DEFAULT NULL, question_type VARCHAR(255) NOT NULL, position INT NOT NULL, submitted_answer LONGTEXT DEFAULT NULL, INDEX IDX_F5ECC5041A0C5AE6 (test_session_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE answer (id INT AUTO_INCREMENT NOT NULL, question_id INT DEFAULT NULL, answer LONGTEXT NOT NULL, is_valid TINYINT(1) NOT NULL, INDEX IDX_DADD4A251E27F6BF (question_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE test_session_template (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, time_limit INT NOT NULL, cutoff_success DOUBLE PRECISION NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE test_session_template_item ADD CONSTRAINT FK_66EDDE4E12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE test_session_template_item ADD CONSTRAINT FK_66EDDE4E93E6FAAB FOREIGN KEY (test_session_template_id) REFERENCES test_session_template (id)');
        $this->addSql('ALTER TABLE question ADD CONSTRAINT FK_B6F7494E12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE test_session ADD CONSTRAINT FK_C05011C93E6FAAB FOREIGN KEY (test_session_template_id) REFERENCES test_session_template (id)');
        $this->addSql('ALTER TABLE test_session_item ADD CONSTRAINT FK_F5ECC5041A0C5AE6 FOREIGN KEY (test_session_id) REFERENCES test_session (id)');
        $this->addSql('ALTER TABLE answer ADD CONSTRAINT FK_DADD4A251E27F6BF FOREIGN KEY (question_id) REFERENCES question (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE test_session_template_item DROP FOREIGN KEY FK_66EDDE4E12469DE2');
        $this->addSql('ALTER TABLE question DROP FOREIGN KEY FK_B6F7494E12469DE2');
        $this->addSql('ALTER TABLE answer DROP FOREIGN KEY FK_DADD4A251E27F6BF');
        $this->addSql('ALTER TABLE test_session_item DROP FOREIGN KEY FK_F5ECC5041A0C5AE6');
        $this->addSql('ALTER TABLE test_session_template_item DROP FOREIGN KEY FK_66EDDE4E93E6FAAB');
        $this->addSql('ALTER TABLE test_session DROP FOREIGN KEY FK_C05011C93E6FAAB');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE test_session_template_item');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE test_session');
        $this->addSql('DROP TABLE test_session_item');
        $this->addSql('DROP TABLE answer');
        $this->addSql('DROP TABLE test_session_template');
    }
}
