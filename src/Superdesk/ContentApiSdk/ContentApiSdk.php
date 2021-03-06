<?php

/**
 * This file is part of the PHP SDK library for the Superdesk Content API.
 *
 * Copyright 2015 Sourcefabric z.u. and contributors.
 *
 * For the full copyright and license information, please see the
 * AUTHORS and LICENSE files distributed with this source code.
 *
 * @copyright 2015 Sourcefabric z.ú.
 * @license http://www.superdesk.org/license
 */

namespace Superdesk\ContentApiSdk;

use Superdesk\ContentApiSdk\Client\ClientInterface;
use Superdesk\ContentApiSdk\Data\Item;
use Superdesk\ContentApiSdk\Data\Package;
use stdClass;

/**
 * Superdesk ContentApi class.
 */
class ContentApiSdk
{
    const SUPERDESK_ENDPOINT_ITEMS = '/items';
    const SUPERDESK_ENDPOINT_PACKAGES = '/packages';

    /**
     * Internal request service.
     *
     * @var ClientInterface
     */
    protected $client;

    /**
     * Construct method for class.
     *
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Get a single item via id.
     *
     * @param string $itemId Identifier for item
     *
     * @return Item
     */
    public function getItem($itemId)
    {
        $body = $this->client->makeApiCall(sprintf('%s/%s', self::SUPERDESK_ENDPOINT_ITEMS, $itemId));

        // TODO: Built in data-type check
        $item = new Item(json_decode($body));

        return $item;
    }

    /**
     * Get multiple items based on a filter.
     *
     * @param array $params Filter parameters
     *
     * @return mixed
     */
    public function getItems($params)
    {
        $return = null;
        $body = $this->client->makeApiCall(self::SUPERDESK_ENDPOINT_ITEMS, $params);

        // TODO: Built in data-type check
        $bodyJSONObj = json_decode($body);


        if (count($bodyJSONObj->_items) > 0) {
            foreach ($bodyJSONObj->_items as $key => $item) {
                $bodyJSONObj->_items[$key] = new Item($item);
            }

            $return = $bodyJSONObj->_items;
        }

        return $return;
    }

    /**
     * Get package by identifier.
     *
     * @param string $packageId    Package identifier
     * @param bool   $resolveItems Inject full associations instead of references
     *                             by uri.
     *
     * @return Package
     */
    public function getPackage($packageId, $resolveItems = false)
    {
        $body = $this->client->makeApiCall(sprintf('%s/%s', self::SUPERDESK_ENDPOINT_PACKAGES, $packageId));

        // TODO: Built in data-type check
        $package = new Package(json_decode($body));

        if ($resolveItems) {
            $associations = $this->getAssociationsFromPackage($package);
            $package = $this->injectAssociations($package, $associations);
        }

        return $package;
    }

    /**
     * Get multiple packages based on a filter.
     *
     * @param array $params       Filter parameters
     * @param bool  $resolveItems Inject full item data in reponse
     *
     * @return mixed
     */
    public function getPackages($params, $resolveItems = false)
    {
        $packages = null;
        $body = $this->client->makeApiCall(self::SUPERDESK_ENDPOINT_PACKAGES, $params);

        // TODO: Built in data-type check
        $bodyJSONObj = json_decode($body);

        if (count($bodyJSONObj->_items) > 0) {
            foreach ($bodyJSONObj->_items as $key => $item) {
                $bodyJSONObj->_items[$key] = new Package($item);
            }
            $packages = $bodyJSONObj->_items;

            if ($resolveItems) {
                foreach ($packages as $id => $package) {
                    $associations = $this->getAssociationsFromPackage($package);
                    $packages[$id] = $this->injectAssociations($package, $associations);
                }
            }
        }

        return $packages;
    }

    /**
     * Gets full objects for all associations for a package.
     *
     * @param  Package $package  A package
     *
     * @return stdClass          List of associations
     */
    private function getAssociationsFromPackage($package)
    {
        $associations = new \stdClass();

        if (isset($package->associations)) {
            foreach ($package->associations as $associatedName => $associatedItem) {
                $associatedId = $this->getIdFromUri($associatedItem->uri);

                // TODO: Check if we can make asynchronous calls here
                if ($associatedItem->type == 'composite') {
                    $associatedObj = $this->getPackage($associatedId, true);
                } else {
                    $associatedObj = $this->getItem($associatedId);
                }

                $associations->$associatedName = $associatedObj;
            }
        }

        return $associations;
    }

    /**
     * Overwrite the associations links in a packages with the actual association
     * data.
     *
     * @param  Package  $package      Package
     * @param  stdClass $associations Multiple items or packages
     *
     * @return Package                Package with data injected
     */
    private function injectAssociations($package, $associations)
    {
        if (count($package->associations) > 0 && count($associations) > 0) {
            $package->associations = $associations;
        }

        return $package;
    }

    /**
     * Tries to find a valid id in an uri, both item as package uris. The id
     * is returned urldecoded.
     *
     * @param  string $uri Item or package uri
     *
     * @return string      Urldecoded id
     */
    public static function getIdFromUri($uri)
    {
        /*
         * Works for package and item uris
         *   http://publicapi:5050/packages/tag%3Ademodata.org%2C0012%3Aninjs_XYZ123
         *   http://publicapi:5050/items/tag%3Ademodata.org%2C0003%3Aninjs_XYZ123
         */

        $uriPath = parse_url($uri, PHP_URL_PATH);
        $objectId = str_replace(ContentApiSdk::getAvailableEndpoints(), '', $uriPath);
        // Remove possible slashes and spaces, since we're working with urls
        $objectId = trim($objectId, '/ ');
        $objectId = urldecode($objectId);

        return $objectId;
    }

    /**
     * Returns a list of all supported endpoint for the Superdesk Publicapi.
     *
     * @return array
     */
    public static function getAvailableEndpoints()
    {
        return array(
            self::SUPERDESK_ENDPOINT_ITEMS,
            self::SUPERDESK_ENDPOINT_PACKAGES,
        );
    }
}
