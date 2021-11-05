# SimpleSchema 

SimpleSchema is a MySQL utility that syncs your database-schema
with a schema defined in file(s).

When designing models, or while prototyping, you may need to perform
a lot of schema changes. You might be familiar with database migration
solutions offered by popular frameworks, while this method is offers
high accuracy and reproducability, it become tidious when you don't
get the migration step right the first time.

A nicer approach would be if you could just write your schema down
in a file and let a tool make the necessary database schema modifications
for you.

This is what simpleschema is all about. 

A sample database definition:
```sql
topics:
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT
        `name` varchar(40) DEFAULT NULL

users:
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT
        `name` varchar(40) DEFAULT NULL


posts:
        `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT
        `name` varchar(255) DEFAULT NULL
        `content` text DEFAULT NULL
        `topic_id` int(11) DEFAULT NULL
        KEY `fk_topic_id_topics_id` (`topic_id`)
        CONSTRAINT `fk_topic_id_topics_id` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`)
```

The format closely follows SQL format but with some annoyances stripped: comma's and CREATE TABLE 
stuff. 

Save this to myschema.simpleschema.txt

Now compare the schema to your database:

`simpleschema diff` 

Which tells you:

```sql
- ALTER TABLE `users` ADD COLUMN `password` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `name`
- ALTER TABLE `posts` MODIFY COLUMN `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `id`
```

And if your content with these changes, run them:

`simpleschema run`

Warning: SimpleSchema can DROP COLUMNS, KEYS, etc. Review the changes CAREFULLY
before applying to prevent UNINTENTED DATA LOSS.

# Making it work
simpleschema checks the following ENVIRONMENT variables:
DB_HOST
DB_PORT
DB_USERNAME
DB_PASSWORD
DB_DATABASE

You can supply these settings as prefixing them to your command:

`DB_HOST=x DB_PORT=3306 DB_USERNAME=user DB_PASSWORD=pass DB_DATABASE=mydb simpleschema run`

Ways to set environment variables: https://phoenixnap.com/kb/linux-set-environment-variable

Or, as a last option, you can add a `simpleschema` to any package.json under the current directory.
This method is suitable for development purposes.

```json
"simpleschema" : {
    "DB_HOST" : "....",
    "DB_PORT" : "...",
    "DB_USERNAME" : "...",
    "DB_PASSWORD" : "...",
    "DB_DATABASE" : "..."
}
```

# Convert an existing schema to simpleschema format

Use `simpleschema export` to get going. Write it to a file by forwarding the output to a file.
`simpleschema export > myschema.simpleschema.txt

# Simpleschema files
the simpleschema binary will `find` files that end contain '.simpleschema.' in there name 
and concatenate all there content. This allows you to store your break up a giant schema
into logical parts and distribute these files inside your codebase. 

Use `simpleschema ls` to list all the simpleschema files in a project.
Use `simpleschema cat` to view the combined output of all the files.

