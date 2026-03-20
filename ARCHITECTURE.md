# Architecture: statsnewsletter

## Purpose

A PrestaShop statistics module that reports newsletter subscription counts over time, split by subscriber type (customer vs. guest) to measure email list growth.

## Directory Structure

```
statsnewsletter.php   - Module class (ModuleGraph subclass); all business logic
upgrade/              - Migration scripts
tests/                - PHPUnit test stubs and PHPStan bootstrap
translations/         - Locale string overrides
```

## Key Design Decisions

- **ModuleGraph inheritance**: Renders subscription growth as a line or bar chart.
- **Two data series**: Tracks registered customers and anonymous guest subscribers separately.

## Extension Points

- Override `getData()` to include opt-out rates or segment by source.

## Dependency Flow

```
statsnewsletter (ModuleGraph)
  └─> hookDisplayAdminStatsModules() — renders the subscription chart
  └─> getData()                      — newsletter subscription query
        └─> Db::getInstance()
```
