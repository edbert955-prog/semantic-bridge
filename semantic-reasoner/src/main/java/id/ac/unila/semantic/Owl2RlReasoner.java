package id.ac.unila.semantic;

import java.io.OutputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;

import org.apache.jena.ontapi.OntModelFactory;
import org.apache.jena.ontapi.OntSpecification;
import org.apache.jena.ontapi.model.OntModel;
import org.apache.jena.rdf.model.InfModel;
import org.apache.jena.rdf.model.Model;
import org.apache.jena.rdf.model.ModelFactory;
import org.apache.jena.riot.Lang;
import org.apache.jena.riot.RDFDataMgr;
import org.apache.jena.riot.RDFFormat;

/**
 * Loads the local workflow/SIAKADU/alignment ontologies plus one evaluation graph,
 * then applies Apache Jena's OWL 2 RL rule-based inference profile.
 * This is separate from Laravel's explicit academic business-rule evaluator.
 */
public final class Owl2RlReasoner {
    private Owl2RlReasoner() {}

    public static void main(String[] args) throws Exception {
        if (args.length != 3) {
            System.err.println("Usage: java -jar semantic-owl2rl-reasoner.jar <ontology-dir> <input.ttl> <output.ttl>");
            System.exit(2);
        }

        Path ontologyDir = Path.of(args[0]).toAbsolutePath().normalize();
        Path input = Path.of(args[1]).toAbsolutePath().normalize();
        Path output = Path.of(args[2]).toAbsolutePath().normalize();
        if (!Files.isDirectory(ontologyDir) || !Files.isRegularFile(input)) {
            throw new IllegalArgumentException("Ontology directory or input Turtle file does not exist.");
        }

        Model base = ModelFactory.createDefaultModel();
        for (String file : new String[] {"workflow.ttl", "siakadu.ttl", "alignment.ttl"}) {
            Path ontology = ontologyDir.resolve(file);
            if (!Files.isRegularFile(ontology)) {
                throw new IllegalArgumentException("Missing ontology file: " + ontology);
            }
            RDFDataMgr.read(base, ontology.toUri().toString(), Lang.TURTLE);
        }
        RDFDataMgr.read(base, input.toUri().toString(), Lang.TURTLE);

        // Jena 6 uses the maintained OntAPI package. The OWL2_RL_MEM_RULES_INF
        // specification attaches Jena's rule-based OWL 2 RL inference engine.
        OntModel ontologyModel = OntModelFactory.createModel(
            base.getGraph(), OntSpecification.OWL2_RL_MEM_RULES_INF
        );
        InfModel inferred = ontologyModel.asInferenceModel();
        inferred.prepare();

        Files.createDirectories(output.getParent());
        Path temporary = output.resolveSibling(output.getFileName() + ".tmp");
        try (OutputStream out = Files.newOutputStream(temporary)) {
            RDFDataMgr.write(out, inferred, RDFFormat.TURTLE_PRETTY);
        }
        Files.move(temporary, output, StandardCopyOption.REPLACE_EXISTING);
        System.out.println("OWL2_RL_REASONING_OK triples=" + inferred.size());

        inferred.close();
        ontologyModel.close();
        base.close();
    }
}
