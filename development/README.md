# Developing simpleschema

You may need a database to test the schema syncing abilities
of simpleschema. 

So to get started quickly:

```bash
# Inside directory development/

docker-compose up -d        # Will create a database on port 9836 to which we can connect
                            # Also phpmyadmin will be avaible at http://localhost:9811

# to make the simpleschema globally available 
npm run install:executable
npm run uninstall:executable

# Now you should be able to run 
simpleschema help
simpleschema man


