# Apache Jena OWL 2 RL helper

This folder contains a small Java CLI invoked by the Laravel application. It loads the local Turtle ontology files and the evaluation graph, executes Apache Jena's `OWL2_RL_MEM_RULES_INF` ontology model profile, and writes an inferred Turtle graph.

## Requirements

- JDK 21 or newer
- Maven 3.9+ available as `mvn` in PowerShell
- Network access during the initial Maven dependency download

## Build on Windows

From this directory:

```powershell
java -version
mvn -version
mvn clean package
```

Expected output:

```text
target/semantic-owl2rl-reasoner.jar
```

Set `SEMANTIC_REASONER_ENABLED=true` in the parent Laravel project's `.env`, point `SEMANTIC_REASONER_JAR` to the built JAR, and run `php artisan optimize:clear`.

If Java/Maven build fails, keep `SEMANTIC_REASONER_ENABLED=false`; Laravel still builds the base RDF file and records `reasoner_status=not_configured`. Do not mark an evaluation as OWL 2 RL processed unless the inference log records `owl2rl_reasoned`.
