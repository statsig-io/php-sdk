## Statsig PHP Server SDK

The php SDK for multi-user, server side environments. If you need a SDK for another language or single user client environment, check out our [other SDKs](https://docs.statsig.com/#sdks).

Statsig helps you move faster with Feature Gates (Feature Flags) and Dynamic Configs. It also allows you to run A/B tests to validate your new features and understand their impact on your KPIs. If you're new to Statsig, create an account at [statsig.com](https://www.statsig.com).

## Getting Started

Check out our [SDK docs](https://docs.statsig.com/server/phpSDK) to get started.

## Incompatabilities

NOTE: The php SDK is for a different webserver environment than other statsig SDKs.  It is currently missing the following features:

- Layers
- ID List based segments

If either of these are important to you, please [reach out in our slack](https://www.statsig.com/slack), file a github issue, or otherwise get our attention.

## Testing

Each server SDK is tested at multiple levels - from unit to integration and e2e tests. Our internal e2e test harness runs daily against each server SDK, while unit and integration tests can be seen in the respective github repos of each SDK. For php, the `/tests/TestE2E.php` runs a validation test on local rule/condition evaluation for php against the results in the statsig backend.

To run all tests:

```
composer install
composer test
```
