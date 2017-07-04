## 0.7.0
 * [Feat] Drop support for PHP 5.5, add support for PHP 7.1

## 0.6.0
 * [Feat] `create-bucket` command
 * [Feat] `delete-bucket` command

## 0.5.7
 * [Fix] `copy-table` fixed creating composite primary key

## 0.5.6
 * [Fix] `copy-table` use async export

## 0.5.5
 * [Feat] `purge-project` filtering options
 * [Feat] `restore-project` filtering options

## 0.5.0
 * [Feat] `purge-project` command
 * [Feat] `restore-project` command
 * [Fix] `backup-project` preserves empty JSON objects

## 0.2.9
 * [Feat] `backup-project` don't merge sliced files

## 0.2.8
 * [Feat] `backup-project` backup only project structure flag added

## 0.2.7
 * [Fix] `backup-project` json format fixed

## 0.2.6
 * [Feat] `backup-project` command added. Allows backup whole project to AWS S3.
 * [Feat] `KBC_RUN_ID` env variable support. Assigns RUN ID to all API calls.

## 0.2.2
 * [Feat] export async and allow gzip

## 0.2.1
 * [Bugfix] phar build fix

## 0.2.0
 * [Feat] copy bucket - set backend for destination bucket

## 0.1.9
 * [Bugfix] Dependencies versions

## 0.1.8
 * [Refactoring] Storage API client minor version auto update allowed

## 0.1.7
 * [Feature] Storage API URL as optional parameter
 * [Bugfix] Copying tables/buckets between projects

## 0.1.6
 * [Feature] Command copy-table is now able to copy tables to another project. Added copy-bucket command.

## 0.1.5
 * [Feature] Restore table from imports command

## 0.1.4
 * [Bugfix] Phar build - added guzzle ca certificates



