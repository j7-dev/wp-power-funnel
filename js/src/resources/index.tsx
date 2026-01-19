import { FaLine, FaRobot } from "react-icons/fa6";
import {
    SettingOutlined,
} from '@ant-design/icons'

export const resources = [
    {
        name: 'promo-links',
        list: '/promo-links',
        create: '/promo-links',
        edit: '/promo-links/:id',
        show: '/promo-links/:id',
        meta: {
            canDelete: true,
            label: 'LINE 連結',
            icon: <FaLine />,
        },
    },
    {
        name: 'workflow-rules',
        list: '/workflow-rules',
        create: '/workflow-rules',
        edit: '/workflow-rules/:id',
        show: '/workflow-rules/:id',
        meta: {
            canDelete: true,
            label: '自動化',
            icon: <FaRobot />,
        },
    },
    {
        name: 'settings',
        list: '/settings',
        meta: {
            label: '設定',
            icon: <SettingOutlined />,
        },
    },
]
