<?php

namespace App\Twig\Loader;

use App\Entity\FrontMatter\FrontMatter;
use App\Repository\FrontMatterRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;
use Twig\Source;

#[AutoconfigureTag('twig.loader', ['priority' => -10])]
readonly class DatabaseLoader implements LoaderInterface
{
    public function __construct(
        private FrontMatterRepository $templateRepository,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getSourceContext(string $name): Source
    {
        $template = $this->getTemplate($name);
        if (null === $template) {
            throw new LoaderError(sprintf('Template "%s" does not exist.', $name));
        }

        return new Source($template->getSource(), $name);
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheKey(string $name): string
    {
        return $name;
    }

    /**
     * {@inheritDoc}
     */
    public function isFresh(string $name, int $time): bool
    {
        $template = $this->getTemplate($name);
        if (null === $template) {
            return false;
        }

        return $template->getLastUpdated()->getTimestamp() <= $time;
    }

    /**
     * {@inheritDoc}
     */
    public function exists(string $name): bool
    {
        return (bool) $this->getTemplate($name);
    }

    protected function getTemplate(string $name): ?FrontMatter
    {
        return $this->templateRepository->findOneBy(['filename' => $name]);
    }
}
