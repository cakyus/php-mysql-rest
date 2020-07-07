# PHP MySQL REST

CAUTION: This repository is unstable / experimental version. You need to watch your step !

A single PHP file that adds REST API to a MySQL database

## Endpoints

### Records

 1. Fetch records `GET /api/v1/table-name`
 2. Create record `POST /api/v1/table-name`
 3. Update record `PUT /api/v1/table-name/{id}`
 4. Delete record `DELETE /api/v1/table-name/{id}`

### Utility

#### Cache

 1. Fetch cache `GET /api/v1/_db/cache`
 2. Create cache `POST /api/v1/_db/cache`
 2. Delete cache `DELETE /api/v1/_db/cache`
