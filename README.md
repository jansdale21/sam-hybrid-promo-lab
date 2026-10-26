# Hybrid Promotional Campaign Laboratory

## Project Overview
A PHP OOP-based promotional email system integrating MySQL, Faker, Amazon SQS, Mailtrap, and Amazon CloudWatch.

## Technologies Used
- PHP 8 and Composer
- MySQL
- Faker
- AWS SQS and Dead-Letter Queue (DLQ)
- Mailtrap Sandbox
- Amazon CloudWatch

## System Workflow
1. Generate 10,000 fictional customers using Faker.
2. Select 50 unique opted-in customers.
3. Prepare promotional email requests in MySQL.
4. Publish requests to Amazon SQS.
5. Process messages using a PHP worker.
6. Send promotional emails through Mailtrap Sandbox.
7. Record processing results in MySQL.
8. Monitor queues and failures using CloudWatch.

## Project Files
- `src/` — PHP OOP classes
- `config.php` — Application configuration
- `seed-customers.php` — Customer generation
- `prepare-campaign.php` — Campaign preparation
- `enqueue.php` — SQS publishing
- `worker.php` — SQS message processing
- `schema.sql` — Database structure
- `iam-policy.json` — AWS IAM policy

## Setup
1. Install PHP, Composer, and MySQL.
2. Run `composer install`.
3. Create the `marketing_lab` database.
4. Import `schema.sql`.
5. Configure local database, AWS, and Mailtrap credentials securely.
6. Run the PHP scripts in workflow order.

## Reliability Features
- Customer consent validation
- Duplicate request handling
- SQS retry and dead-letter queue
- Processing history
- CloudWatch monitoring and alarms

## Security
Credentials, generated logs, and customer records must not be committed to GitHub.