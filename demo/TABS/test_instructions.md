🧪 How to Run the Tests

Make sure PHPUnit is installed:

composer require --dev phpunit/phpunit


Run tests from the project root:

vendor/bin/phpunit --colors=always --testdox


Expected output:

SessionAdminServer
 ✔ Stores and retrieves session data per tab
 ✔ Returns default value if key not found
 ✔ Can destroy specific tab session
 ✔ Marks tab as inactive
 ✔ Returns debug data with expected structure
 ✔ Returns expected session key
 ✔ Handles multiple tab sessions isolated

🧩 Optional: phpunit.xml file (recommended)

Add this to your project root for easy test configuration:

<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true" verbose="true">
    <testsuites>
        <testsuite name="SessionAdminServer Test Suite">
            <directory>./tests</directory>
        </testsuite>
    </testsuites>
    <php>
        <ini name="session.save_path" value="/tmp" />
    </php>
</phpunit>


Then simply run:

vendor/bin/phpunit