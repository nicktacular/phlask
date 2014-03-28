# Release notes

## Version 0.2.1-alpha
This is an API breaking release.

 - `TaskSpecInterface` now includes `getId` method to allow for the `StatusNotifierInterface` to correctly link up with existing task specs.
 - The `pushTask` method was removed from the `TaskQueueInterface` since this is not a concern of the consumer.

## Version 0.1.2-alpha

The initial alpha release which is compatible with PHP 5.3.27 and above.