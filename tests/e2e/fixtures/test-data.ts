/**
 * Test Data — E2E 測試常數與 Mock 資料
 */

/* ── Base ── */
export const BASE_URL = 'http://localhost:8894'

/* ── API Namespace / Endpoints ── */
export const API_NS = 'power-funnel/v1'
export const WP_API_BASE = 'wp/v2'

export const EP = {
  activities: `${API_NS}/activities`,
  options: `${API_NS}/options`,
  revokeGoogleOAuth: `${API_NS}/revoke-google-oauth`,
  liff: `${API_NS}/liff`,
  lineCallback: `${API_NS}/line-callback`,
  // WP CPT REST API
  promoLinks: `${WP_API_BASE}/pf_promo_link`,
  workflowRules: `${WP_API_BASE}/pf_workflow_rule`,
  registrations: `${WP_API_BASE}/pf_registration`,
  workflows: `${WP_API_BASE}/pf_workflow`,
} as const

/* ── LINE Mock Data ── */
export const LINE_SETTINGS = {
  liff_id: '[E2E] 1234567890-abcdefgh',
  liff_url: 'https://liff.line.me/1234567890-abcdefgh',
  channel_id: '[E2E] 1234567890',
  channel_secret: '[E2E] secret123',
  channel_access_token: '[E2E] token123',
} as const

export const LINE_USER = {
  userId: '[E2E] U_test_user_001',
  name: '[E2E] 測試用戶',
  picture: 'https://example.com/avatar.jpg',
  os: 'iOS',
  version: '2.0.0',
  lineVersion: '13.0.0',
  isInClient: true,
  isLoggedIn: true,
} as const

export const LIFF_PAYLOAD_FULL = {
  ...LINE_USER,
  urlParams: { promoLinkId: '10' },
} as const

export const LIFF_PAYLOAD_MINIMAL = {
  userId: '[E2E] U_test_user_002',
  name: '[E2E] 最小資料用戶',
  isInClient: false,
  isLoggedIn: true,
} as const

/* ── YouTube / Google OAuth Mock Data ── */
export const YOUTUBE_SETTINGS = {
  clientId: '[E2E] client-id-123',
  clientSecret: '[E2E] client-secret-456',
  redirectUri: `${BASE_URL}/wp-admin/admin.php?page=power-funnel`,
} as const

/* ── Workflow Test Data ── */
export const WORKFLOW_NODE_EMAIL = {
  id: 'n1',
  node_definition_id: 'email',
  params: {
    recipient: 'context',
    subject_tpl: '[E2E] 歡迎',
    content_tpl: '[E2E] 感謝報名',
  },
  match_callback: ['__return_true'],
  match_callback_params: [],
} as const

export const WORKFLOW_NODE_INVALID = {
  id: 'n_invalid',
  node_definition_id: 'non_existent_node',
  params: {},
  match_callback: ['__return_true'],
  match_callback_params: [],
} as const

/* ── LINE Webhook Mock Events ── */
export const LINE_WEBHOOK_POSTBACK_EVENT = {
  destination: 'U0123456789abcdef0123456789abcdef',
  events: [
    {
      type: 'postback',
      timestamp: Date.now(),
      source: { type: 'user', userId: '[E2E] U_test_user_001' },
      replyToken: '00000000000000000000000000000000',
      postback: {
        data: 'action=register&activity_id=yt001&promo_link_id=10',
      },
    },
  ],
} as const

export const LINE_WEBHOOK_MESSAGE_EVENT = {
  destination: 'U0123456789abcdef0123456789abcdef',
  events: [
    {
      type: 'message',
      timestamp: Date.now(),
      source: { type: 'user', userId: '[E2E] U_test_user_001' },
      replyToken: '00000000000000000000000000000000',
      message: { id: '1234567890', type: 'text', text: '[E2E] 你好' },
    },
  ],
} as const

/* ── Edge Case Strings ── */
export const EDGE = {
  // 多語系字元
  unicode: '日本語テスト 한국어 العربية',
  rtlText: 'مرحبا بالعالم',               // RTL 文字（阿拉伯文）
  chineseText: '中文測試',
  japaneseText: '日本語テスト',

  // 特殊字元
  emoji: '🎉🚀💥🔥✅❌🤖🧪',
  emojiMixed: '🎉🚀💰',

  // 惡意輸入 — XSS
  specialChars: '<script>alert("xss")</script>',
  xssImg: '<img onerror=alert(1) src=x>',

  // 惡意輸入 — SQL injection
  sqlInjection: "'; DROP TABLE wp_options; --",
  sqlOr: "' OR 1=1 --",
  sqlDrop: "'; DROP TABLE wp_posts; --",

  // 路徑穿越
  pathTraversal: '../../wp-config.php',

  // NULL byte 注入
  nullByte: 'test\x00inject',

  // 長度邊界
  longString: 'A'.repeat(10_000),
  longStringShort: 'A'.repeat(5_000),

  // HTML 實體
  htmlEntities: '&lt;b&gt;bold&lt;/b&gt;',

  // 空值
  emptyString: '',
  whitespaceOnly: '   \t\n  ',

  // 數值邊界
  maxInt: Number.MAX_SAFE_INTEGER,
  negativeInt: -1,
  negativeInt2: -999,
  zero: 0,
  floatValue: 0.5,
  largePositive: 999999,
} as const

/* ── Response Code Constants ── */
export const CODES = {
  getOptionsSuccess: 'get_options_success',
  saveOptionsSuccess: 'save_options_success',
  revokeGoogleOAuthSuccess: 'revoke_google_oauth_success',
  liffSuccess: 'success',
} as const

/* ── Activity DTO Expected Fields ── */
export const ACTIVITY_DTO_FIELDS = [
  'id',
  'activity_provider_id',
  'title',
  'description',
  'thumbnail_url',
  'scheduled_start_time',
  'meta',
] as const
