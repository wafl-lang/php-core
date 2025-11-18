<?php

declare(strict_types=1);

namespace Wafl\Core;

/**
 * High-level orchestrator that turns a .wafl file into a validated PHP array.
 *
 * It wires together the loader, resolver, evaluator, and schema validator so
 * consumers only need to call a single method.
 */
final class ConfigLoader
{
    private Loader $loader;
    private Resolver $resolver;
    private DocumentEvaluator $evaluator;
    private SchemaValidator $validator;
    private TypeMetadataExtractor $typeMetadataExtractor;

    /**
     * @param Loader|null $loader Allows injecting a custom loader (useful in tests)
     * @param Resolver|null $resolver Allows overriding tag/expression behaviour
     * @param DocumentEvaluator|null $evaluator Evaluates @eval blocks and lists
     * @param SchemaValidator|null $validator Validates the resolved document
     * @param TypeMetadataExtractor|null $typeMetadataExtractor Extracts key<Type> hints
     */
    public function __construct(
        ?Loader $loader = null,
        ?Resolver $resolver = null,
        ?DocumentEvaluator $evaluator = null,
        ?SchemaValidator $validator = null,
        ?TypeMetadataExtractor $typeMetadataExtractor = null
    ) {
        $this->loader = $loader ?? new Loader();
        $this->resolver = $resolver ?? new Resolver();
        $this->evaluator = $evaluator ?? new DocumentEvaluator();
        $this->validator = $validator ?? new SchemaValidator();
        $this->typeMetadataExtractor = $typeMetadataExtractor ?? new TypeMetadataExtractor();
    }

    /**
     * Loads and processes a .wafl file from disk.
     *
     * @param string $filePath Path to the entry file
     * @param array $options Optional environment and symbol table
     *
     * @return array{config: array, meta: array}
     */
    public function load(string $filePath, array $options = []): array
    {
        $env = $options['env'] ?? $_ENV;
        $symbols = $options['symbols'] ?? [];

        $loaded = $this->loader->load($filePath);
        $doc = $loaded['doc'];
        $meta = $loaded['meta'];

        $typeMetadata = $this->typeMetadataExtractor->extract($doc);
        $resolved = $this->resolver->resolve($doc, [
            'env' => $env,
            'ctx' => ['baseDir' => $meta['baseDir'] ?? dirname($filePath)],
        ]);

        $evaluated = $this->evaluator->evaluate($resolved, [
            'env' => $env,
            'symbols' => $symbols,
            'baseDir' => $meta['baseDir'] ?? dirname($filePath),
        ]);

        $schema = $meta['schema'] ?? ($evaluated['@schema'] ?? null);
        if ($schema !== null && is_array($schema)) {
            $this->validator->validate($evaluated, $schema, $typeMetadata);
        }

        return [
            'config' => $evaluated,
            'meta' => [
                'imports' => $meta['imports'] ?? [],
                'schema' => $schema,
                'evalBlock' => $meta['evalBlock'] ?? null,
                'baseDir' => $meta['baseDir'] ?? null,
            ],
        ];
    }
}
