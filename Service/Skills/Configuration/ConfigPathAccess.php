<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Service\Skills\Configuration;

use Magento\Config\Model\Config\Structure;
use Magento\Framework\AuthorizationInterface;
use Magento\Theme\Model\Design\Config\MetadataProviderInterface;

/**
 * What config_reader and config_writer share: the Magento ACL resource that gates a configuration
 * path, and the paths that stay off limits whatever the admin holds.
 *
 * A path is gated by the resource its section declares in system.xml - Magento_Payment::payment
 * for payment methods, Magento_Config::config_admin for the admin URL and session settings,
 * Magento_Config::web for whether the admin panel enforces HTTPS - resolved the way the admin
 * configuration save controller resolves it: a field's <config_path> can place its value in a
 * different section than the one that declares the field, so the config path is first mapped back
 * to the sections declaring it. The broad Magento_Config::config only says the admin may open the
 * configuration area at all, which any admin holding one section does, so it is not a gate on its
 * own and is only answered when no path is named. A section that declares no resource, or a path
 * outside any section, resolves to '' and is refused (#200).
 *
 * The blocklist is defense in depth for credential-shaped paths: holding a section is no reason to
 * pass its API keys and secrets through chat.
 */
final class ConfigPathAccess
{
    public const AREA_RESOURCE = 'Magento_Config::config';

    private const BLOCKED_PATTERNS = [
        '*key*', '*secret*', '*password*', '*token*', '*credential*',
        'payment/*', '*api_key*', '*private*', '*encrypt*',
    ];

    private const BLOCKED_WORDS = ['key', 'secret', 'password', 'token', 'credential', 'private', 'encrypt'];

    private const BLOCKED_PREFIX = 'payment/';

    public function __construct(
        private readonly Structure $structure,
        private readonly AuthorizationInterface $authorization,
        private readonly MetadataProviderInterface $designConfig
    ) {
    }

    /**
     * Whether the admin holds the resource of the section this path belongs to.
     *
     * ToolAccess answers the same question before the call, but on the input as the model sent
     * it; execute() receives it with privacy tokens rehydrated, so a path that only becomes itself
     * there is checked again on its final value (#222).
     *
     * @param string $path
     * @return bool
     */
    public function isAllowed(string $path): bool
    {
        $resource = $this->aclResourceFor($path);

        return $resource !== '' && $this->authorization->isAllowed($resource);
    }

    /**
     * Whether an admin screen stores this path, the test the configuration save applies before
     * writing (Save::filterNodes): a path no field declares is a row no admin screen shows or can
     * change back. Fields count by the path they store under (their config_path when they have
     * one), a group that clones its fields accepts any field name, and the design section's fields
     * live in Content > Design > Configuration, not in system.xml.
     *
     * @param string $path
     * @return bool
     */
    public function isDeclared(string $path): bool
    {
        if (isset($this->structure->getFieldPaths()[$path])) {
            return true;
        }
        foreach ($this->designConfig->get() as $field) {
            if (($field['path'] ?? null) === $path) {
                return true;
            }
        }

        return $this->isInCloningGroup($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isInCloningGroup(string $path): bool
    {
        $segments = explode('/', $path);
        for ($depth = 2; $depth < count($segments); $depth++) {
            $group = $this->structure->getElement(implode('/', array_slice($segments, 0, $depth)));
            if (!empty($group?->getData()['clone_fields'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The path as both config tools check and use it: surrounding whitespace and slashes dropped.
     * Case is kept, since the row is stored as named; a section id in the wrong case matches no
     * section and is refused.
     *
     * @param mixed $path
     * @return string
     */
    public function normalise(mixed $path): string
    {
        return is_string($path) ? trim($path, " \t\n\r\0\x0B/") : '';
    }

    /**
     * The resource an admin needs for the section a path belongs to.
     *
     * '' when no section the path can be edited in declares one, and the configuration area's own
     * resource when no path is named.
     *
     * @param string $path A config path: "web/secure/use_in_adminhtml"
     * @return string
     */
    public function aclResourceFor(string $path): string
    {
        if ($path === '') {
            return self::AREA_RESOURCE;
        }

        foreach ($this->sectionsEditing($path) as $sectionId) {
            $resource = (string)($this->structure->getElement($sectionId)?->getData()['resource'] ?? '');
            if ($resource !== '') {
                return $resource;
            }
        }

        return '';
    }

    /**
     * The sections a config path is edited in: every section declaring a field that stores under
     * it (a field's <config_path> can point into another section, and PayPal declares its fields
     * once per country in sections extending "payment", not all of which carry the resource), then
     * the path's own section.
     *
     * @param string $configPath
     * @return string[]
     */
    private function sectionsEditing(string $configPath): array
    {
        return array_unique(array_map(
            static fn (string $structurePath): string => explode('/', $structurePath)[0],
            [...($this->structure->getFieldPaths()[$configPath] ?? []), $configPath]
        ));
    }

    /**
     * @param string $path
     * @return bool
     */
    public function isBlocked(string $path): bool
    {
        $pathLower = strtolower($path);
        foreach (self::BLOCKED_PATTERNS as $pattern) {
            $regex = '/^' . str_replace(['*', '/'], ['.*', '\/'], $pattern) . '$/';
            if (preg_match($regex, $pathLower)) {
                return true;
            }
        }
        foreach (explode('/', $pathLower) as $segment) {
            foreach (self::BLOCKED_WORDS as $word) {
                if (str_contains($segment, $word)) {
                    return true;
                }
            }
        }

        return str_starts_with($pathLower, self::BLOCKED_PREFIX);
    }
}
