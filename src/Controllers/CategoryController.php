<?php //strict

namespace IO\Controllers;

use IO\Api\ResponseCode;
use IO\Helper\RouteConfig;
use IO\Guards\AuthGuard;
use IO\Helper\Utils;
use IO\Services\SessionStorageService;
use IO\Services\UrlService;
use Plenty\Modules\ShopBuilder\Helper\ShopBuilderRequest;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Log\Loggable;

/**
 * Class CategoryController
 * @package IO\Controllers
 */
class CategoryController extends LayoutController
{
    use Loggable;

    /**
     * Prepare and render the data for categories
     * @param string $lvl1 Level 1 of category url. Will be null at root page
     * @param string $lvl2 Level 2 of category url.
     * @param string $lvl3 Level 3 of category url.
     * @param string $lvl4 Level 4 of category url.
     * @param string $lvl5 Level 5 of category url.
     * @param string $lvl6 Level 6 of category url.
     * @return string
     */
    public function showCategory(
        $lvl1 = null,
        $lvl2 = null,
        $lvl3 = null,
        $lvl4 = null,
        $lvl5 = null,
        $lvl6 = null)
    {
        $lang       = Utils::getLang();
        $webstoreId = Utils::getWebstoreId();
        $category   = $this->categoryRepo->findCategoryByUrl($lvl1, $lvl2, $lvl3, $lvl4, $lvl5, $lvl6, $webstoreId, $lang);

        /** @var ShopBuilderRequest $shopBuilderRequest */
        $shopBuilderRequest = pluginApp(ShopBuilderRequest::class);
        if ($shopBuilderRequest->isShopBuilder() && ($shopBuilderRequest->getPreviewContentType() === 'singleitem' || $category->type === 'item'))
        {
            /*
             * TODO
             * Remove check for category type when ceres is ready to handle item categories.
             * Right now we need to display single item each time we open an item category in the shop builder to avoid loading non-editable pages
             */
            /** @var ItemController $itemController */
            $itemController = pluginApp(ItemController::class);
            return $itemController->showItemForCategory($category);
        }

        return $this->renderCategory(
            $category
        );
	}

	public function showCategoryById($categoryId)
    {
        /** @var SessionStorageService $sessionService */
        $sessionService  = pluginApp(SessionStorageService::class);
        $lang = $sessionService->getLang();

        return $this->renderCategory(
            $this->categoryRepo->get( $categoryId, $lang )
        );
    }

    public function redirectToCategory( $categoryId, $defaultUrl = "" )
    {
        /** @var SessionStorageService $sessionService */
        $sessionService  = pluginApp(SessionStorageService::class);
        $lang = $sessionService->getLang();

        /** @var UrlService $urlService */
        $urlService = pluginApp(UrlService::class);
        $categoryUrl = $urlService->getCategoryURL( $categoryId, $lang );
        if($categoryUrl->equals($defaultUrl))
        {
            // category url equals legacy route name
            return $this->showCategoryById($categoryId);
        }

        $category = $this->categoryRepo->get($categoryId, $lang);

        if(is_null($category))
        {
            /** @var Response $response */
            $response = pluginApp(Response::class);
            $response->forceStatus(ResponseCode::NOT_FOUND);

            return $response;
        }

        return $urlService->redirectTo(
            $categoryUrl->toRelativeUrl()
        );
    }

	private function renderCategory($category)
    {
        /** @var Request $request */
        $request = pluginApp(Request::class);

        if ($category === null || (($category->clients->count() == 0 || $category->details->count() == 0) && !$this->app->isAdminPreview()))
        {
            $this->getLogger(__CLASS__)->warning(
                "IO::Debug.CategoryController_cannotDisplayCategory",
                [
                    "category" => $category,
                    "clientCount" => ($category !== null ? $category->clients->count() : 0),
                    "detailCount" => ($category !== null ? $category->details->count() : 0),
                    "isAdminPreview" => $this->app->isAdminPreview()
                ]
            );

            /** @var Response $response */
            $response = pluginApp(Response::class);
            $response->forceStatus(ResponseCode::NOT_FOUND);

            return $response;
        }

        $this->categoryService->setCurrentCategory($category);
        if ($this->categoryService->isHidden($category->id)) {
            $guard = pluginApp(AuthGuard::class);
            $guard->assertOrRedirect( true, '/login');
        }

        /** @var ShopBuilderRequest $shopBuilderRequest */
        $shopBuilderRequest = pluginApp(ShopBuilderRequest::class);
        $shopBuilderRequest->setMainContentType($category->type);
        $shopBuilderRequest->setMainCategory($category->id);

        if ( RouteConfig::getCategoryId( RouteConfig::CHECKOUT ) === $category->id || $shopBuilderRequest->getPreviewContentType() === 'checkout')
        {
            $this->getLogger(__CLASS__)->info(
                "IO::Debug.CategoryController_showCheckoutCategory",
                [
                    "category" => $category,
                    "previewContentType" => $shopBuilderRequest->getPreviewContentType()
                ]
            );
            RouteConfig::overrideCategoryId(RouteConfig::CHECKOUT, $category->id);

            /** @var CheckoutController $checkoutController */
            $checkoutController = pluginApp(CheckoutController::class);
            return $checkoutController->showCheckout( $category );
        }

        if ( RouteConfig::getCategoryId( RouteConfig::MY_ACCOUNT ) === $category->id || $shopBuilderRequest->getPreviewContentType() === 'myaccount')
        {
            $this->getLogger(__CLASS__)->info(
                "IO::Debug.CategoryController_showMyAccountCategory",
                [
                    "category" => $category,
                    "previewContentType" => $shopBuilderRequest->getPreviewContentType()
                ]
            );
            RouteConfig::overrideCategoryId(RouteConfig::MY_ACCOUNT, $category->id);

            /** @var MyAccountController $myAccountController */
            $myAccountController = pluginApp(MyAccountController::class);
            return $myAccountController->showMyAccount( $category );
        }

        if ( RouteConfig::getCategoryId( RouteConfig::CONFIRMATION ) === $category->id || $shopBuilderRequest->getPreviewContentType() === 'orderconfirmation')
        {
            $this->getLogger(__CLASS__)->info(
                "IO::Debug.CategoryController_showConfirmationCategory",
                [
                    "category" => $category,
                    "previewContentType" => $shopBuilderRequest->getPreviewContentType()
                ]
            );
            RouteConfig::overrideCategoryId(RouteConfig::CONFIRMATION, $category->id);

            /** @var ConfirmationController $confirmationController */
            $confirmationController = pluginApp(ConfirmationController::class);
            return $confirmationController->showConfirmation($request->get('orderId', 0), $request->get('accessKey', ''), $category);
        }

        return $this->renderTemplate(
            "tpl.category." . $category->type,
            [
                'category'      => $category,
                'sorting'       => $request->get('sorting', null),
                'itemsPerPage'  => $request->get('items', null),
                'page'          => $request->get('page', null),
                'facets'        => $request->get('facets', '')
            ]
        );
    }
}
