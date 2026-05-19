# tl;dr

This solves the problem of having to remember to run "upgrade" tasks (database migrations) for your silverstripe install on every instance.

There are two ways to add a `BuildTask` to the list:

## Option 1: make it implement an interface (best for code you can change)

```php


class MyBuildTask extends BuildTask implements 


## Option 2: add it to a list of 
