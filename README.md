# PHP MySQL REST

CAUTION: This repository is unstable / experimental version. You need to watch your step !

A single PHP file that adds REST API to a MySQL database

## Endpoints

### Records

 1. Fetch records `GET /api/v1/table-name`
 2. Create record `POST /api/v1/table-name`
 3. Update record `PUT /api/v1/table-name/{id}`
 4. Delete record `DELETE /api/v1/table-name/{id}`

#### Fetch Records

##### Pagination

`GET /api/v1/table-name?offset=0&limit=10`

The `limit` clause constrain the number of rows returned.
The `offset` clause specifies offset of the first row to return.

##### Filter

`GET /api/v1/table-name?filter[column-name][operator][]=column-value`

Operators:

 1. `equal` is equal to
 2. `notEqual` is not equal to
 3. `greaterThan` is greater than
 4. `greaterThanOrEqual` is greater than or equal to
 5. `lessThan` is less than
 6. `lessThanOrEqual` is less than or equal to
 7. `beginWith` is less than or equal to
 8. `endWith` is less than or equal to
 9. `between` is less than or equal to
 10. `contain` is less than or equal to
 11. `notContain` is less than or equal to
 12. `isNotNull` is less than or equal to
 13. `isNull` is less than or equal to
 14. `in` is less than or equal to

### Cache

 1. Fetch cache `GET /api/v1/_db/cache`
 2. Create cache `POST /api/v1/_db/cache`
 2. Delete cache `DELETE /api/v1/_db/cache`
