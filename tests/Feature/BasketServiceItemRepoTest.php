<?php

namespace IO\Tests\Feature;

use IO\Services\ItemSearch\Helper\ResultFieldTemplate;
use IO\Services\ItemSearch\SearchPresets\BasketItems;
use IO\Services\ItemSearch\Services\ItemSearchService;
use Mockery;
use IO\Tests\TestCase;
use IO\Services\BasketService;
use Plenty\Modules\Basket\Hooks\BasketItem\CheckNewItemQuantity;
use Plenty\Modules\Frontend\Session\Events\AfterSessionCreate;
use Plenty\Modules\Item\Stock\Hooks\CheckItemStock;
use Plenty\Modules\Item\Variation\Models\Variation;
use Plenty\Modules\Basket\Models\Basket;
use Illuminate\Foundation\Testing\RefreshDatabase;


use Illuminate\Support\Facades\Session;
use Plenty\Modules\Item\VariationDescription\Contracts\VariationDescriptionRepositoryContract;
use Plenty\Modules\Item\VariationDescription\Models\VariationDescription;
use Plenty\Plugin\Events\Dispatcher;

/**
 * User: mklaes
 * Date: 08.08.18
 */
class BasketServiceItemRepoTest extends TestCase
{
    use RefreshDatabase;


    /** @var BasketService $basketService  */
    protected $basketService;
    protected $variation;
    protected $variationStock;

    /** @var ItemSearchService $itemSearchServiceMock  */
    protected $itemSearchServiceMock;

    protected $basketRepoMock;

    protected function setUp(): void
    {
       parent::setUp();

        $this->createApplication();

       $checkItemStockMockery = Mockery::mock(CheckItemStock::class);
       $checkItemStockMockery->shouldReceive('handle')->andReturn();
       $this->replaceInstanceByMock(CheckItemStock::class, $checkItemStockMockery);

       $checkNewItemQuantityMockery = Mockery::mock(CheckNewItemQuantity::class);
       $checkNewItemQuantityMockery->shouldReceive('handle')->andReturn();
       $this->replaceInstanceByMock(CheckNewItemQuantity::class, $checkNewItemQuantityMockery);


       $this->itemSearchServiceMock = Mockery::mock(ItemSearchService::class);
       $this->replaceInstanceByMock(ItemSearchService::class, $this->itemSearchServiceMock);

       $this->basketService = pluginApp(BasketService::class);
       $this->variation = factory(Variation::class)->create([
            'minimumOrderQuantity' => 1.00
       ]);

       $esMockData = $this->getTestJsonData();
       $esMockData['documents'][0]['id'] =  $this->variation['id'];
       $esMockData['documents'][0]['data']['variation']['id'] = $this->variation['id'];

       /**
       * @var ResultFieldTemplate $resultFieldTemplate
       */
       $resultFieldTemplate = pluginApp(ResultFieldTemplate::class);
       $resultFieldTemplate->setTemplates([ResultFieldTemplate::TEMPLATE_BASKET_ITEM   => 'Ceres::ResultFields.BasketItem']);
       $this->replaceInstanceByMock(ResultFieldTemplate::class, $resultFieldTemplate);

       $basketItemsMock = Mockery::mock(BasketItems::class);
       $basketItemsMock->shouldReceive('getSearchFactory')->with([])->andReturn([]);
       $this->replaceInstanceByMock(BasketItems::class, $basketItemsMock);

       $this->itemSearchServiceMock
            ->shouldReceive('getResults')
            ->with(Mockery::any())
            ->andReturn($esMockData);

       $variationDescriptionRepoMock = Mockery::mock(VariationDescriptionRepositoryContract::class);
       $variationDescriptionRepoMock->shouldReceive('find')->andReturn(
           factory(VariationDescription::class)
       );
       $this->replaceInstanceByMock(VariationDescriptionRepositoryContract::class, $variationDescriptionRepoMock);

       $basket = factory(Basket::class)->create();
       Session::shouldReceive('getId')
            ->andReturn($basket->sessionId);

       /** @var Dispatcher $eventDispatcher */
       $eventDispatcher = pluginApp(Dispatcher::class);
       $eventDispatcher->fire(pluginApp(AfterSessionCreate::class));
    }

    /** @test */
    public function it_adds_an_item_to_the_basket()
    {
        $this->markTestSkipped('Needs to be fixed later - variation requires a price!');
        
        $item1 = ['variationId' => $this->variation['id'], 'quantity' => 1, 'template' => '', 'basketItemOrderParams' => [] ];

        $this->basketService->addBasketItem($item1);

        $result = $this->basketService->getBasketItemsForTemplate('', false);
        $this->assertEquals($this->variation['id'], $result[0]['variationId']);
        $this->assertEquals(1, $result[0]['quantity']);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_updates_an_item_in_the_basket()
    {
        $this->markTestSkipped('Needs to be fixed later');

        $item1 = ['variationId' => $this->variation['id'], 'quantity' => 1, 'template' => ''];

        $this->basketService->addBasketItem($item1);
        $this->basketService->addBasketItem($item1);

        $result = $this->basketService->getBasketItemsForTemplate('', false);
        $this->assertEquals($this->variation['id'], $result[0]['variationId']);
        $this->assertEquals(2, $result[0]['quantity']);
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_removes_an_item_from_the_basket()
    {
        $this->markTestSkipped('Needs to be fixed later - variation requires a price!');
        
        $item1 = ['variationId' => $this->variation['id'], 'quantity' => 1, 'template' => ''];

        $this->basketService->addBasketItem($item1);
        $basketItems = $this->basketService->getBasketItemsForTemplate('', false);
        $this->basketService->deleteBasketItem($basketItems[0]['id']);
        $result = $this->basketService->getBasketItemsForTemplate('', false);

        $this->assertEmpty($result);
    }

    /**
     * helper method to get the item search result json
     * @return mixed
     */
    public function getTestJsonData()
    {
        $file = __DIR__ . "/../Fixtures/complete_basket_response.json";
        return json_decode(
            file_get_contents($file),
            true
        );
    }
}
