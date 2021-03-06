<?php
namespace Genkgo\Xsl;

use Closure;
use DOMDocument;
use Genkgo\Cache\CallbackCacheInterface;
use Genkgo\Xsl\Callback\PhpCallback;

/**
 * Class Transpiler
 * @package Genkgo\Xsl
 */
final class Transpiler
{
    /**
     * @var TransformationContext
     */
    private $context;
    /**
     * @var CallbackCacheInterface
     */
    private $cacheAdapter;
    /**
     * @var bool
     */
    private $disableEntities;

    /**
     * @param TransformationContext $context
     * @param CallbackCacheInterface $cacheAdapter
     * @param bool $disableEntities
     */
    public function __construct(
        TransformationContext $context,
        CallbackCacheInterface $cacheAdapter = null,
        bool $disableEntities = true
    )
    {
        $this->context = $context;
        $this->cacheAdapter = $cacheAdapter;
        $this->disableEntities = $disableEntities;
    }

    /**
     * @return string
     */
    public function transpileRoot()
    {
        $document = $this->context->getDocument();

        $callback = function () use ($document) {
            if ($this->disableEntities && $document->doctype && $document->doctype->entities->length > 0) {
                throw new \DOMException('Invalid document, contains entities');
            }

            $this->transpile($document);
            return $document->saveXML();
        };

        $documentURI = $document->documentURI;
        // @codeCoverageIgnoreStart
        if (PHP_OS === 'WINNT') {
            $documentURI = ltrim(str_replace('file:', '', $documentURI), '/');
        }
        // @codeCoverageIgnoreEnd

        if ($this->cacheAdapter !== null && is_file($documentURI)) {
            return $this->cacheAdapter->get($documentURI, $callback);
        }

        return $callback();
    }

    /**
     * @param DOMDocument $document
     */
    public function transpile(DOMDocument $document)
    {
        $root = $document->documentElement;
        if ($root === null) {
            return;
        }

        $transformers = $this->context->getTransformers();
        foreach ($transformers as $transformer) {
            $transformer->transform($document);
        }

        if ($root && $root->getAttribute('version') !== '1.0') {
            $root->setAttribute('version', '1.0');
        }
    }

    /**
     * @param $path
     * @return string
     */
    public function transpileFile($path)
    {
        $callback = function () use ($path) {
            $document = new DOMDocument();
            $document->substituteEntities = false;
            $document->resolveExternals = false;
            $document->load($path);

            if ($this->disableEntities && $document->doctype && $document->doctype->entities->length > 0) {
                throw new \DOMException('Invalid document, contains entities');
            }

            $this->transpile($document);
            return $document->saveXML();
        };

        if ($this->cacheAdapter !== null) {
            return $this->cacheAdapter->get($path, $callback);
        }

        return $callback();
    }

    /**
     * @param Closure $nativeTransformation
     * @return mixed
     */
    public function transform(Closure $nativeTransformation)
    {
        PhpCallback::set($this->context);

        $result = $nativeTransformation();

        PhpCallback::reset();
        return $result;
    }
}
