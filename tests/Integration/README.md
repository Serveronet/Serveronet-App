# Integration Tests

## Running Tests

### Run all tests:
```bash
./vendor/bin/pest
```

### Run only Integration tests:
```bash
./vendor/bin/pest --group=Integration
```

### Run specific test file:
```bash
./vendor/bin/pest tests/Integration/SiteIntegrationTest.php
```

### List available tests:
```bash
./vendor/bin/pest --list-tests --group=Integration
```

## Prerequisites

Tests make HTTP requests to remote servers. Ensure the following servers are running:
- **Site Owner Node**: `hostname:19081` (port 19601 alias)
- **Peer Node**: `hostname:19082` (port 19602 alias)
- **Leech Node**: `hostname:19083` (port 19603 alias)

