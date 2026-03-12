/**
 * Global Setup — E2E 測試初始化
 * 1. Apply LC bypass
 * 2. Login as admin & save nonce
 * 3. Save initial settings
 * 4. Save test IDs to .auth/test-ids.json
 */
import * as fs from 'fs'
import * as path from 'path'
import { applyLcBypass } from './helpers/lc-bypass.js'
import { loginAsAdmin } from './helpers/admin-setup.js'
import { BASE_URL } from './fixtures/test-data.js'

const AUTH_DIR = path.resolve(import.meta.dirname, '.auth')
const TEST_IDS_FILE = path.join(AUTH_DIR, 'test-ids.json')

async function globalSetup() {
  console.log('\n🚀 E2E Global Setup')

  // Ensure .auth directory exists
  if (!fs.existsSync(AUTH_DIR)) {
    fs.mkdirSync(AUTH_DIR, { recursive: true })
  }

  // Step 1: Apply license check bypass
  try {
    applyLcBypass()
  } catch (e) {
    console.warn('LC bypass 跳過:', (e as Error).message)
  }

  // Step 2: Login as admin and save nonce
  console.log('🔐 登入管理員帳號...')
  const nonce = await loginAsAdmin(BASE_URL)
  console.log(`✅ Nonce: ${nonce.slice(0, 6)}...`)

  // Step 3 & 4: Save test IDs (placeholder for dynamic IDs created during setup)
  const testIds: Record<string, string> = {
    setupTimestamp: new Date().toISOString(),
    baseURL: BASE_URL,
    nonce,
  }

  fs.writeFileSync(TEST_IDS_FILE, JSON.stringify(testIds, null, 2))
  console.log('✅ Test IDs saved to .auth/test-ids.json')

  console.log('🎉 Global Setup 完成\n')
}

export default globalSetup
