<?php

namespace App\Service;

use App\DataTransformer\CaseJson\PackageTransformer;
use App\DTO\CaseJson\CFPackage;
use App\Entity\Framework\LsDoc;
use Swaggest\JsonSchema\Schema;
use Symfony\Component\Serializer\SerializerInterface;

class CaseImport
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly PackageTransformer $packageTransformer,
    ) {
    }

    public function importCaseFile(string $content): LsDoc
    {
        ini_set('memory_limit', '4096M');
        set_time_limit(900); // increase time limit for large files

        try {
            $this->validate($content);
        } catch (\Throwable $e) {
            // Try to fix things based on common issues seen
            try {
                $newContent = $this->fixupContent($content);
                $this->validate($newContent);
                $content = $newContent;
            } catch (\Throwable) {
                // Ignore error and throw original
                throw $e;
            }
        }

        /** @var CFPackage $package */
        $package = $this->serializer->deserialize($content, CFPackage::class, 'json');

        return $this->packageTransformer->transform($package);
    }

    private function validate(string $content): void
    {
        $schema = Schema::import(json5_decode(file_get_contents(__DIR__.'/../../config/schema/case-v1p0-cfpackage-schema.json')));
        $schema->in(json5_decode($content));
        $schema = null;
    }

    private function fixupContent(string $content): string
    {
        $json = json5_decode($content, true);

        $teks = 1 === preg_match('/teks-api/', $json['CFDocument']['uri']);

        $items = [];
        foreach (($json['CFItems'] ?? []) as $key => $item) {
            // Some TX frameworks have a bad URI for items where it is missing the / after CFItems
            if ($teks && 1 === preg_match('/CFItems[0-9a-fA-F]/', $item['uri'])) {
                $item['uri'] = preg_replace('/CFItems/', 'CFItems/', $item['uri']);
                $json['CFItems'][$key]['uri'] = $item['uri'];
            }

            // Save URIs for items
            if (isset($item['identifier']) && isset($item['uri'])) {
                $items[$item['identifier']] = $item['uri'];
            }

            // Some TX frameworks are missing fullStatement but have alternativeLabel
            if (empty($item['fullStatement']) && (!empty($item['alternativeLabel']) || !empty($item['abbreviatedStatement']))) {
                $json['CFItems'][$key]['fullStatement'] = $item['alternativeLabel'] ?? $item['abbreviatedStatement'];
            }
        }

        // Try fixing up for issue we have seen in the CASE Network where the URI is the identifier instead of a URI
        // but the item has the URI available
        foreach (($json['CFAssociations'] ?? []) as $key => $association) {
            $node = $association['originNodeURI'];
            if ($node['identifier'] === $node['uri']) {
                $json['CFAssociations'][$key]['originNodeURI']['uri'] = $items[$node['identifier']] ?? $node['uri'];
            }

            $node = $association['destinationNodeURI'];
            if ($node['identifier'] === $node['uri']) {
                $json['CFAssociations'][$key]['destinationNodeURI']['uri'] = $items[$node['identifier']] ?? $node['uri'];
            }

            // Fixup for a couple frameworks we have seen that have bad association types
            if ('RelatedTo' === $association['associationType']) {
                $json['CFAssociations'][$key]['associationType'] = 'isRelatedTo';
            }
        }

        // Fixup for some frameworks from mt.satchelcommons.com where the hierarchyCode is an integer instead of a string
        foreach (($json['CFDefinitions']['CFItemTypes'] ?? []) as $key => $itemType) {
            if (is_int($itemType['hierarchyCode'] ?? null) || is_float($itemType['hierarchyCode'] ?? null)) {
                $json['CFDefinitions']['CFItemTypes'][$key]['hierarchyCode'] = (string) $itemType['hierarchyCode'];
            }
        }

        return json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
