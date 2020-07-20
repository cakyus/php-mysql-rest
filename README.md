# PHP MySQL REST

CAUTION: This repository is unstable / experimental version. You need to watch your step !

A single PHP file that adds REST API to a MySQL database

## Endpoints

### Records

| HTTP Method | Example | Description |
|----|----|----|
| `GET` | `/api/v1/table` | Fetch Records |
| `POST` | `/api/v1/table` | Create Record |
| `PUT` | `/api/v1/table/{id}` | Update Record |
| `DELETE` | `/api/v1/table/{id}` | Delete Record |

#### Fetch Records

##### Pagination

`GET /api/v1/table?offset=0&limit=10`

###### Arguments

| Name | Type | Required | Description |
|----|----|----|----|
| limit | integer | NO | Constrain the number of rows returned |
| offset | integer | NO | Specifies offset of the first row to return |

##### Filter

`GET /api/v1/table?filter[column-name][operator][]=column-value`

###### Oprerators

| Name | Description |
|----|----|
| `equal` | is equal to |
| `notEqual` | is not equal to |
| `greaterThan` | is greater than |
| `greaterThanOrEqual` | is greater than or equal to |
| `lessThan` | is less than |
| `lessThanOrEqual` | is less than or equal to |
| `beginWith` | is less than or equal to |
| `endWith` | is less than or equal to |
| `between` | is less than or equal to |
| `contain` | is less than or equal to |
| `notContain` | is less than or equal to |
| `isNotNull` | is less than or equal to |
| `isNull` | is less than or equal to |
| `in` | is less than or equal to |

### Cache

 1. Fetch cache `GET /api/v1/_db/schema`
 2. Create cache `POST /api/v1/_db/schema`

| HTTP Method | Example | Description |
|----|----|----|
| `GET` | `/api/v1/_db/schema` | Fetch schema cache |
| `POST` | `/api/v1/_db/schema` | Create shcema cache |
