<?php
namespace TYPO3\TYPO3CR\Tests\Functional\Eel\FlowQueryOperations;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "TYPO3.TYPO3CR".         *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3 of the   *
 * License, or (at your option) any later version.                        *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\TYPO3CR\Tests\Functional\AbstractNodeTest;

/**
 * Functional test case which tests FlowQuery FilterOperation
 */
class FilterOperationTest extends AbstractNodeTest
{
    /**
     * @test
     */
    public function noFilterReturnsAllNodesInContext()
    {
        $q = new FlowQuery(array($this->node, $this->node->getNode('products')));
        $foundNodes = $q->filter('')->get();
        $this->assertEquals(2, count($foundNodes));
    }

    /**
     * @test
     */
    public function filterByNodeObjectIsSupported()
    {
        $q = new FlowQuery(array($this->node, $this->node->getNode('products')));
        $foundNodes = $q->filter($this->node)->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
    }

    /**
     * @test
     */
    public function propertyNameFilterIsSupported()
    {
        $q = new FlowQuery(array($this->node, $this->node->getNode('products')));
        $foundNodes = $q->filter('home')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->children('x')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function multiplePropertyNameFiltersIsSupported()
    {
        $productsNode = $this->node->getNode('products');
        $q = new FlowQuery(array($this->node, $productsNode));
        $foundNodes = $q->filter('home, products')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertSame($productsNode, $foundNodes[1]);
        $this->assertEquals(2, count($foundNodes));
        $foundNodes = $q->filter('home, x')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('x, products')->get();
        $this->assertSame($productsNode, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('x, x')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function identityFilterIsSupported()
    {
        $q = new FlowQuery(array($this->node, $this->node->getNode('products')));
        $foundNodes = $q->filter('#3239baee-3e7f-785c-0853-f4302ef32570')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('#xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function multipleIdentityFiltersIsSupported()
    {
        $productsNode = $this->node->getNode('products');
        $q = new FlowQuery(array($this->node, $productsNode));
        $foundNodes = $q->filter('#3239baee-3e7f-785c-0853-f4302ef32570, #25eaba22-b8ed-11e3-a8b5-c82a1441d728')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertSame($productsNode, $foundNodes[1]);
        $this->assertEquals(2, count($foundNodes));
        $foundNodes = $q->filter('#3239baee-3e7f-785c-0853-f4302ef32570, #xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('#xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx, #25eaba22-b8ed-11e3-a8b5-c82a1441d728')->get();
        $this->assertSame($productsNode, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('#xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx, #xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function attributeFilterUsingPropertyIsSupported()
    {
        $q = new FlowQuery(array($this->node));
        $foundNodes = $q->filter('[title *= "Home"]')->get();
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('[title *= "x"]')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function attributeFilterUsingInternalPropertyIsSupported()
    {
        $productsNode = $this->node->getNode('products');
        $q = new FlowQuery(array($this->node, $productsNode));
        $foundNodes = $q->filter('[_depth = 3]')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('[_depth = 4]')->get();
        $this->assertSame($productsNode, $foundNodes[0]);
        $this->assertEquals(1, count($foundNodes));
        $foundNodes = $q->filter('[_depth = 5]')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function instanceofFilterUsingNodeTypeIsSupported()
    {
        $productsNode = $this->node->getNode('products');
        $teaserNode = $this->node->getNode('teaser');
        $sidebarNode = $this->node->getNode('sidebar');
        $q = new FlowQuery(array($this->node, $productsNode, $teaserNode, $sidebarNode));
        $foundNodes = $q->filter('[instanceof TYPO3.TYPO3CR.Testing:Page]')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertSame($productsNode, $foundNodes[1]);
        $this->assertEquals(2, count($foundNodes));
        $foundNodes = $q->filter('[instanceof TYPO3.TYPO3CR.Testing:ContentCollection]')->get();
        $this->assertSame($teaserNode, $foundNodes[0]);
        $this->assertSame($sidebarNode, $foundNodes[1]);
        $this->assertEquals(2, count($foundNodes));
        $foundNodes = $q->filter('[instanceof X]')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function twoInstanceofFiltersUsingNodeTypeIsSupported()
    {
        $productsNode = $this->node->getNode('products');
        $teaserNode = $this->node->getNode('teaser');
        $sidebarNode = $this->node->getNode('sidebar');
        $q = new FlowQuery(array($this->node, $productsNode, $teaserNode, $sidebarNode));
        $foundNodes = $q->filter('[instanceof TYPO3.TYPO3CR.Testing:Document][instanceof TYPO3.TYPO3CR.Testing:Page]')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertSame($productsNode, $foundNodes[1]);
        $this->assertEquals(2, count($foundNodes));
        $foundNodes = $q->filter('[instanceof X][instanceof TYPO3.TYPO3CR.Testing:Page]')->get();
        $this->assertEquals(0, count($foundNodes));
    }

    /**
     * @test
     */
    public function multipleInstanceofFiltersUsingNodeTypeIsSupported()
    {
        $productsNode = $this->node->getNode('products');
        $teaserNode = $this->node->getNode('teaser');
        $sidebarNode = $this->node->getNode('sidebar');
        $q = new FlowQuery(array($this->node, $productsNode, $teaserNode, $sidebarNode));
        $foundNodes = $q->filter('[instanceof TYPO3.TYPO3CR.Testing:Page], [instanceof TYPO3.TYPO3CR.Testing:ContentCollection]')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertSame($productsNode, $foundNodes[1]);
        $this->assertSame($teaserNode, $foundNodes[2]);
        $this->assertSame($sidebarNode, $foundNodes[3]);
        $this->assertEquals(4, count($foundNodes));
    }

    /**
     * @test
     */
    public function negatedInstanceofFilterUsingNodeTypeIsSupported()
    {
        $productsNode = $this->node->getNode('products');
        $teaserNode = $this->node->getNode('teaser');
        $sidebarNode = $this->node->getNode('sidebar');
        $q = new FlowQuery(array($this->node, $productsNode, $teaserNode, $sidebarNode));
        $foundNodes = $q->filter('[instanceof !TYPO3.TYPO3CR.Testing:ContentCollection]')->get();
        $this->assertSame($this->node, $foundNodes[0]);
        $this->assertSame($productsNode, $foundNodes[1]);
        $this->assertEquals(2, count($foundNodes));
        $foundNodes = $q->filter('[instanceof !TYPO3.TYPO3CR.Testing:Page]')->get();
        $this->assertSame($teaserNode, $foundNodes[0]);
        $this->assertSame($sidebarNode, $foundNodes[1]);
        $this->assertEquals(2, count($foundNodes));
        $foundNodes = $q->filter('[instanceof !X]')->get();
        $this->assertEquals(4, count($foundNodes));
    }
}
