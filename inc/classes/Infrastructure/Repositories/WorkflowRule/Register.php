<?php

declare ( strict_types = 1 );

namespace J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule;

use J7\PowerFunnel\Contracts\DTOs\WorkflowRuleDTO;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\BaseNodeDefinition;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\EmailNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\SmsNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\LineNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\WebhookNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\WaitNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\WaitUntilNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\TimeWindowNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\YesNoBranchNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\SplitBranchNode;
use J7\PowerFunnel\Infrastructure\Repositories\WorkflowRule\NodeDefinitions\TagUserNode;

/** Class Register */
final class Register {

	private const POST_TYPE = 'pf_workflow_rule';

	/** 註冊 hooks */
	public static function register_hooks(): void {
		\add_action( 'init', [ __CLASS__, 'register_cpt' ] );
		\add_action( 'init', [ __CLASS__, 'register_meta_fields' ] );
		\add_filter('power_funnel/workflow_rule/node_definitions', [ __CLASS__, 'register_default_node_definitions' ]);
	}

	/** Register cpt */
	public static function register_cpt(): void {

		$args = [
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'query_var'          => true,
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => [ 'title', 'custom-fields' ],
		];

		// @phpstan-ignore-next-line
		\register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * 註冊 meta 欄位，暴露至 REST API
	 */
	public static function register_meta_fields(): void {
		\register_post_meta(
			self::POST_TYPE,
			'trigger_point',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'        => 'string',
						'description' => '觸發點設定：純字串（hook 名稱）或 JSON 字串（{hook, params} 物件）',
					],
				],
				'sanitize_callback' => [ __CLASS__, 'sanitize_trigger_point' ],
				'auth_callback'     => static fn() => \current_user_can( 'edit_posts' ),
			]
		);

		\register_post_meta(
			self::POST_TYPE,
			'nodes',
			[
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'node_module'    => [ 'type' => 'string' ],
								'node_type'      => [ 'type' => 'string' ],
								'sort'           => [ 'type' => 'integer' ],
								'args'           => [
									'type'                 => 'object',
									'additionalProperties' => true,
								],
								'match_callback' => [ 'type' => 'string' ],
							],
						],
					],
				],
				'auth_callback' => static fn() => \current_user_can( 'edit_posts' ),
			]
		);
	}

	/** 取得 post_type */
	public static function post_type(): string {
		return self::POST_TYPE;
	}

	/**
	 * 清理 trigger_point meta 值
	 * 支援舊版純字串格式與新版 JSON 物件格式 {hook: string, params: {...}}
	 *
	 * @param mixed $value 待清理的值
	 * @return string 清理後的字串
	 */
	public static function sanitize_trigger_point( mixed $value ): string {
		if (\is_array($value)) {
			// 新版物件格式：序列化為 JSON 字串
			$hook   = isset($value['hook']) && \is_string($value['hook']) ? \sanitize_text_field($value['hook']) : '';
			$params = isset($value['params']) && \is_array($value['params']) ? \array_map('sanitize_text_field', $value['params']) : [];
			return (string) \wp_json_encode([ 'hook' => $hook, 'params' => $params ]);
		}
		if (\is_string($value)) {
			// 嘗試解析 JSON 字串格式
			$decoded = \json_decode($value, true);
			if (\is_array($decoded)) {
				$hook   = isset($decoded['hook']) && \is_string($decoded['hook']) ? \sanitize_text_field($decoded['hook']) : '';
				$params = isset($decoded['params']) && \is_array($decoded['params']) ? $decoded['params'] : [];
				return (string) \wp_json_encode([ 'hook' => $hook, 'params' => $params ]);
			}
			// 舊版純字串格式
			return \sanitize_text_field($value);
		}
		return '';
	}


	/** @return bool 是否為活動報名 post */
	public static function match( \WP_Post $post ): bool {
		return $post->post_type === self::POST_TYPE;
	}

	/** 註冊所有已發布的 WorkflowRule */
	public static function register_workflow_rules(): void {
		/** @var array<WorkflowRuleDTO> $workflow_rules */
		$workflow_rules = Repository::get_publish_workflow_rules();
		foreach ($workflow_rules as $workflow_rule) {
			$workflow_rule->register();
		}
	}

	/**
	 * 註冊預設的 node definitions
	 *
	 * @param array<string, BaseNodeDefinition> $node_definitions 現有的 node definitions
	 * @return array<string, BaseNodeDefinition> 新增後的 node definitions
	 */
	public static function register_default_node_definitions( array $node_definitions ): array {
		$definitions = [
			new EmailNode(),
			new SmsNode(),
			new LineNode(),
			new WebhookNode(),
			new WaitNode(),
			new WaitUntilNode(),
			new TimeWindowNode(),
			new YesNoBranchNode(),
			new SplitBranchNode(),
			new TagUserNode(),
		];
		foreach ($definitions as $definition) {
			$node_definitions[ $definition->id ] = $definition;
		}
		return $node_definitions;
	}
}
