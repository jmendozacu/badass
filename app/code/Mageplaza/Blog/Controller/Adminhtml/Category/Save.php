<?php
/**
 * Mageplaza_Blog extension
 *                     NOTICE OF LICENSE
 *
 *                     This source file is subject to the MIT License
 *                     that is bundled with this package in the file LICENSE.txt.
 *                     It is also available through the world-wide-web at this URL:
 *                     http://opensource.org/licenses/mit-license.php
 *
 *                     @category  Mageplaza
 *                     @package   Mageplaza_Blog
 *                     @copyright Copyright (c) 2016
 *                     @license   http://opensource.org/licenses/mit-license.php MIT License
 */
namespace Mageplaza\Blog\Controller\Adminhtml\Category;

class Save extends \Mageplaza\Blog\Controller\Adminhtml\Category
{
    /**
     * Result Raw Factory
     *
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $resultRawFactory;

    /**
     * Result Json Factory
     *
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * Layout Factory
     *
     * @var \Magento\Framework\View\LayoutFactory
     */
    protected $layoutFactory;

    /**
     * JS helper
     *
     * @var \Magento\Backend\Helper\Js
     */
    protected $jsHelper;

    /**
     * constructor
     *
     * @param \Magento\Framework\Controller\Result\RawFactory $resultRawFactory
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Framework\View\LayoutFactory $layoutFactory
     * @param \Magento\Backend\Helper\Js $jsHelper
     * @param \Mageplaza\Blog\Model\CategoryFactory $categoryFactory
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Backend\Model\View\Result\RedirectFactory $resultRedirectFactory
     * @param \Magento\Backend\App\Action\Context $context
     */
    public function __construct(
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Backend\Helper\Js $jsHelper,
        \Mageplaza\Blog\Model\CategoryFactory $categoryFactory,
        \Magento\Framework\Registry $registry,
        \Magento\Backend\Model\View\Result\RedirectFactory $resultRedirectFactory,
        \Magento\Backend\App\Action\Context $context
    ) {
    
        $this->resultRawFactory  = $resultRawFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->layoutFactory     = $layoutFactory;
        $this->jsHelper          = $jsHelper;
        parent::__construct($categoryFactory, $registry, $resultRedirectFactory, $context);
    }

    /**
     * run the action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $data = $this->getRequest()->getPost('category');
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            $category = $this->initCategory();

            if (!$category) {
                return $resultRedirect->setPath('mageplaza_blog/*/', ['_current' => true, 'id' => null]);
            }
            $category->addData($data);
            if ($posts = $this->getRequest()->getPost('category_posts')) {
                $posts = json_decode($posts, true);
                $category->setPostsData($posts);
            }
            $refreshTree = false;
            if (!$category->getId()) {
                $parentId = $this->getRequest()->getParam('parent');
                if (!$parentId) {
                    $parentId = \Mageplaza\Blog\Model\Category::TREE_ROOT_ID;
                }
                $parentCategory = $this->categoryFactory->create()->load($parentId);
                $category->setPath($parentCategory->getPath());
                $category->setParentId($parentId);
            }

            $this->_eventManager->dispatch(
                'mageplaza_blog_category_prepare_save',
                ['category' => $category, 'request' => $this->getRequest()]
            );

            try {
                $category->save();
                $this->messageManager->addSuccess(__('You saved the Category.'));
                $refreshTree = true;
            } catch (\Exception $e) {
                $this->messageManager->addError($e->getMessage());
                $this->_getSession()->setMageplazaBlogCategoryData($data);
                $refreshTree = false;
            }

            if ($this->getRequest()->getPost('return_session_messages_only')) {
                $category->load($category->getId());
                // to obtain truncated Category Name
                /** @var $block \Magento\Framework\View\Element\Messages */
                $block = $this->layoutFactory->create()->getMessagesBlock();
                $block->setMessages($this->messageManager->getMessages(true));

                /** @var \Magento\Framework\Controller\Result\Json $resultJson */
                $resultJson = $this->resultJsonFactory->create();
                return $resultJson->setData(
                    [
                        'messages' => $block->getGroupedHtml(),
                        'error' => !$refreshTree,
                        'category' => $category->toArray(),
                    ]
                );
            }
            $redirectParams = [
                '_current' => true,
                'category_id' => $category->getId()
            ];
            return $resultRedirect->setPath(
                'mageplaza_blog/*/edit',
                $redirectParams
            );
        }
        $redirectParams = ['_current' => true];
        return $resultRedirect->setPath(
            'mageplaza_blog/*/edit',
            $redirectParams
        );
    }
}
