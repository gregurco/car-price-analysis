# Car Price Analysis

## Install 

```
yarn install

composer install
```

## Deploy

```
node_modules/.bin/serverless deploy
```

## Prepare data

```
aws dynamodb scan --table-name cars > export.json
```
