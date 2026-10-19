# Automated tests

The GitHub Actions workflow in `.github/workflows/automated-tests.yml` runs the checks on pushes, pull requests, and manual dispatches. It uses PHP's built-in development server and Node's built-in test runner; no test packages or database credentials are required.

The suite is divided into:

- **Unit tests:** PHP helper behavior and JavaScript storage, cart, escaping, and money-formatting utilities.
- **System tests:** HTTP availability of every HTML page and each locally referenced script, stylesheet, or image.
- **Performance tests:** 95th-percentile response time for the homepage and shared application script, with a 2-second ceiling.
- **Stress tests:** 80 simultaneous requests to the homepage and marketplace, with a 20-second completion limit.

To run the HTTP tests locally, start `php -S 127.0.0.1:8080 -t .` in the repository root. In another terminal, run:

```sh
php tests/unit.php
node --test tests/unit.test.cjs
node --test tests/system.test.cjs
node --test tests/performance.test.cjs
node --test tests/stress.test.cjs
```
