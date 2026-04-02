<?php
/**
 * TagUserNode 表單欄位整合測試。
 *
 * 驗證 TagUserNode 的 form_fields 定義，
 * 確認 tags 欄位類型為 tags_input，action 欄位仍為 select。
 *
 * @group node-system
 * @group tag-user-node
 *
 * @see specs/implement-node-definitions/features/nodes/tag-user-form-fields.feature
 */

declare(strict_types=1);

namespace J7\PowerFunnel\Tests\Integration\NodeSystem;

use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\TagUserNode;
use J7\PowerFunnel\Tests\Integration\IntegrationTestCase;

/**
 * TagUserNode 表單欄位測試
 *
 * Feature: TagUserNode 表單欄位更新
 */
class TagUserFormFieldsTest extends IntegrationTestCase {

	/** @var TagUserNode 被測節點定義 */
	private TagUserNode $tag_user_node;

	/** 初始化依賴 */
	protected function configure_dependencies(): void {
		\J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\Register::register_hooks();
	}

	/** 每個測試前設置 */
	public function set_up(): void {
		parent::set_up();
		$this->tag_user_node = new TagUserNode();
	}

	/**
	 * Feature: TagUserNode 表單欄位更新
	 * Rule: 後置（狀態）- tags 欄位型別應為 tags_input
	 * Example: TagUserNode form_fields 定義
	 */
	public function test_tags欄位型別應為tags_input(): void {
		// Given 系統已建立 TagUserNode
		$form_fields = $this->tag_user_node->form_fields;

		// Then tags 欄位應存在
		$this->assertArrayHasKey( 'tags', $form_fields, 'form_fields 應包含 tags 欄位' );

		$tags_field = $form_fields['tags'];

		// And tags 欄位的 type 應為 "tags_input"
		$this->assertSame( 'tags_input', $tags_field->type, 'tags 欄位 type 應為 tags_input' );

		// And tags 欄位的 name 應為 "tags"
		$this->assertSame( 'tags', $tags_field->name, 'tags 欄位 name 應為 tags' );

		// And tags 欄位的 label 應為 "標籤"
		$this->assertSame( '標籤', $tags_field->label, 'tags 欄位 label 應為 標籤' );

		// And tags 欄位的 required 應為 true
		$this->assertTrue( $tags_field->required, 'tags 欄位 required 應為 true' );

		// And tags 欄位不應有 options（或 options 為空）
		$options = $tags_field->options ?? [];
		$this->assertEmpty( $options, 'tags_input 欄位不應有 options' );
	}

	/**
	 * Feature: TagUserNode 表單欄位更新
	 * Rule: 後置（狀態）- action 欄位應保持不變
	 * Example: action 欄位仍為 select
	 */
	public function test_action欄位仍為select(): void {
		// Given 系統已建立 TagUserNode
		$form_fields = $this->tag_user_node->form_fields;

		// Then action 欄位應存在
		$this->assertArrayHasKey( 'action', $form_fields, 'form_fields 應包含 action 欄位' );

		$action_field = $form_fields['action'];

		// And action 欄位的 type 應為 "select"
		$this->assertSame( 'select', $action_field->type, 'action 欄位 type 應為 select' );

		// And action 欄位的 options 應包含 "add" 和 "remove"
		$options = $action_field->options ?? [];
		$this->assertNotEmpty( $options, 'action 欄位應有 options' );

		$option_values = \array_column( $options, 'value' );
		$this->assertContains( 'add', $option_values, 'action options 應包含 add' );
		$this->assertContains( 'remove', $option_values, 'action options 應包含 remove' );
	}
}
