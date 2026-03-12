/**
 * Test Data — E2E 測試常數與 Mock 資料
 */

/* ── Base ── */
export const BASE_URL = 'http://localhost:8894'

/* ── API Namespace / Endpoints ── */
export const API_NS = 'power-funnel/v1'
export const EP = {
  activities: `${API_NS}/activities`,
  options: `${API_NS}/options`,
  revokeGoogleOAuth: `${API_NS}/revoke-google-oauth`,
  liff: `${API_NS}/liff`,
  lineCallback: `${API_NS}/line-callback`,
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
  unicode: '日本語テスト 한국어 العربية',
  emoji: '🎉🚀💥🔥✅❌🤖🧪',
  longString: 'A'.repeat(10_000),
  specialChars: '<script>alert("xss")</script>',
  sqlInjection: "'; DROP TABLE wp_options; --",
  htmlEntities: '&lt;b&gt;bold&lt;/b&gt;',
  emptyString: '',
  whitespaceOnly: '   \t\n  ',
  nullishValues: { nullVal: null, undefinedVal: undefined },
  maxInt: Number.MAX_SAFE_INTEGER,
  negativeInt: -1,
  zero: 0,
  floatValue: 3.14159,
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
