<?php

/**
 * File containing the UrlAlias Handler implementation.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Persistence\Cache;

use eZ\Publish\API\Repository\Exceptions\NotFoundException as APINotFoundException;
use eZ\Publish\Core\Base\Exceptions\NotFoundException;
use eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler as UrlAliasHandlerInterface;
use eZ\Publish\SPI\Persistence\Content\UrlAlias;

/**
 * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler
 */
class UrlAliasHandler extends AbstractHandler implements UrlAliasHandlerInterface
{
    /**
     * Constant used for storing not found results for lookup().
     */
    const NOT_FOUND = 0;

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::publishUrlAliasForLocation
     */
    public function publishUrlAliasForLocation(
        $locationId,
        $parentLocationId,
        $name,
        $languageCode,
        $alwaysAvailable = false,
        $updatePathIdentificationString = false
    ) {
        $this->logger->logCall(
            __METHOD__,
            array(
                'location' => $locationId,
                'parent' => $parentLocationId,
                'name' => $name,
                'language' => $languageCode,
                'alwaysAvailable' => $alwaysAvailable,
            )
        );

        $this->persistenceHandler->urlAliasHandler()->publishUrlAliasForLocation(
            $locationId,
            $parentLocationId,
            $name,
            $languageCode,
            $alwaysAvailable,
            $updatePathIdentificationString
        );

        $this->cache->invalidateTags(['urlAlias-location-'.$locationId, 'urlAlias-notFound']);
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::createCustomUrlAlias
     */
    public function createCustomUrlAlias($locationId, $path, $forwarding = false, $languageCode = null, $alwaysAvailable = false)
    {
        $this->logger->logCall(
            __METHOD__,
            array(
                'location' => $locationId,
                '$path' => $path,
                '$forwarding' => $forwarding,
                'language' => $languageCode,
                'alwaysAvailable' => $alwaysAvailable,
            )
        );

        $urlAlias = $this->persistenceHandler->urlAliasHandler()->createCustomUrlAlias(
            $locationId,
            $path,
            $forwarding,
            $languageCode,
            $alwaysAvailable
        );

        $this->cache->invalidateTags(['urlAlias-location-'.$locationId, 'urlAlias-notFound']);

        return $urlAlias;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::createGlobalUrlAlias
     */
    public function createGlobalUrlAlias($resource, $path, $forwarding = false, $languageCode = null, $alwaysAvailable = false)
    {
        $this->logger->logCall(
            __METHOD__,
            array(
                'resource' => $resource,
                'path' => $path,
                'forwarding' => $forwarding,
                'language' => $languageCode,
                'alwaysAvailable' => $alwaysAvailable,
            )
        );

        $urlAlias = $this->persistenceHandler->urlAliasHandler()->createGlobalUrlAlias(
            $resource,
            $path,
            $forwarding,
            $languageCode,
            $alwaysAvailable
        );

        $this->cache->invalidateTags(['urlAlias-global-'.$languageCode, 'urlAlias-notFound']);

        return $urlAlias;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::listGlobalURLAliases
     */
    public function listGlobalURLAliases($languageCode = null, $offset = 0, $limit = -1)
    {
        $this->logger->logCall(__METHOD__, array('language' => $languageCode, 'offset' => $offset, 'limit' => $limit));

        return $this->persistenceHandler->urlAliasHandler()->listGlobalURLAliases($languageCode, $offset, $limit);
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::listURLAliasesForLocation
     */
    public function listURLAliasesForLocation($locationId, $custom = false)
    {

        $cacheItem = $this->cache->getItem('ez-urlAlias-location-list-'.$locationId.($custom ? '-custom' : ''));
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $this->logger->logCall(__METHOD__, array('location' => $locationId, 'custom' => $custom));
        $urlAliases = $this->persistenceHandler->urlAliasHandler()->listURLAliasesForLocation($locationId, $custom);

        $cacheItem->set($urlAliases);
        $cacheTags = ['urlAlias-location-'.$locationId];
        foreach ($urlAliases as $urlAlias) {
            $cacheTags[] = 'urlAlias-'.$urlAlias->id;
        }
        $cacheItem->tag($cacheTags);
        $this->cache->save($cacheItem);

        return $urlAliases;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::removeURLAliases
     */
    public function removeURLAliases(array $urlAliases)
    {
        $this->logger->logCall(__METHOD__, array('aliases' => $urlAliases));
        $return = $this->persistenceHandler->urlAliasHandler()->removeURLAliases($urlAliases);

        $cacheTags = [];
        foreach ($urlAliases as $urlAlias) {
            $cacheTags[] = 'urlAlias-'.$urlAlias->id;
            if ($urlAlias->type === UrlAlias::LOCATION) {
                $cacheTags[] = 'urlAlias-location-'.$urlAlias->destination;
            }
            if ($urlAlias->isCustom) {
                $cacheTags[] = 'urlAlias-custom-'.$urlAlias->destination;
            }
        }
        $this->cache->invalidateTags($cacheTags);

        return $return;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::lookup
     */
    public function lookup($url)
    {
        $cacheItem = $this->cache->getItem('ez-urlAlias-url-'.str_replace('/', '_', $url));
        if ($cacheItem->isHit()) {
            if (($return = $cacheItem->get()) === self::NOT_FOUND) {
                throw new NotFoundException('UrlAlias', $url);
            }

            return $return;
        }

        $this->logger->logCall(__METHOD__, array('url' => $url));
        try {
            $urlAlias = $this->persistenceHandler->urlAliasHandler()->lookup($url);
        } catch (APINotFoundException $e) {
            $cacheItem->set(self::NOT_FOUND)
                ->expiresAfter(30)
                ->tag(['urlAlias-notFound']);
            $this->cache->save($cacheItem);
            throw $e;
        }

        $cacheItem->set($urlAlias);
        $cachTags = ['urlAlias-'.$urlAlias->id];
        if ($urlAlias->type === UrlAlias::LOCATION) {
            $cachTags[] = 'urlAlias-location-'.$urlAlias->destination;
        }
        $cacheItem->tag($cachTags);
        $this->cache->save($cacheItem);


        return $urlAlias;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::loadUrlAlias
     */
    public function loadUrlAlias($id)
    {
        $cacheItem = $this->cache->getItem('ez-urlAlias-', $id);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $this->logger->logCall(__METHOD__, array('alias' => $id));
        $urlAlias = $this->persistenceHandler->urlAliasHandler()->loadUrlAlias($id);

        $cacheItem->set($urlAlias);
        if ($urlAlias->type === UrlAlias::LOCATION) {
            $cacheItem->tag(['urlAlias-'.$urlAlias->id, 'urlAlias-location-'.$urlAlias->destination]);
        } else {
            $cacheItem->tag(['urlAlias-'.$urlAlias->id]);
        }
        $this->cache->save($cacheItem);


        return $urlAlias;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::locationMoved
     */
    public function locationMoved($locationId, $oldParentId, $newParentId)
    {
        $this->logger->logCall(
            __METHOD__,
            array(
                'location' => $locationId,
                'oldParent' => $oldParentId,
                'newParent' => $newParentId,
            )
        );

        $return = $this->persistenceHandler->urlAliasHandler()->locationMoved($locationId, $oldParentId, $newParentId);

        $this->cache->invalidateTags(['urlAlias-location-'.$locationId]);

        return $return;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::locationCopied
     */
    public function locationCopied($locationId, $newLocationId, $newParentId)
    {
        $this->logger->logCall(
            __METHOD__,
            array(
                'oldLocation' => $locationId,
                'newLocation' => $newLocationId,
                'newParent' => $newParentId,
            )
        );

        $return = $this->persistenceHandler->urlAliasHandler()->locationCopied(
            $locationId,
            $newLocationId,
            $newParentId
        );
        $this->cache->invalidateTags(['urlAlias-location-'.$locationId, 'urlAlias-location-'.$newLocationId]);

        return $return;
    }

    /**
     * @see eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::locationDeleted
     */
    public function locationDeleted($locationId)
    {
        $this->logger->logCall(__METHOD__, array('location' => $locationId));
        $return = $this->persistenceHandler->urlAliasHandler()->locationDeleted($locationId);

        $this->cache->invalidateTags(['urlAlias-location-'.$locationId]);

        return $return;
    }

    /**
     * @see \eZ\Publish\SPI\Persistence\Content\UrlAlias\Handler::swap
     */
    public function locationSwapped($location1ParentId, $location1Id, $location2ParentId, $location2Id)
    {
        $this->logger->logCall(
            __METHOD__,
            [
                'location1ParentId' => $location1ParentId,
                'location1Id' => $location1Id,
                'location2ParentId' => $location2ParentId,
                'location2Id' => $location2Id,
            ]
        );

        $return = $this->persistenceHandler->urlAliasHandler()->locationSwapped(
            $location1ParentId,
            $location1Id,
            $location2ParentId,
            $location2Id
        );

        $this->cache->invalidateTags(['urlAlias-location-'.$location1ParentId, 'urlAlias-location-'.$location2ParentId]);

        return $return;
    }
}
