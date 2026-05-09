<div align="center">

# Typecho-Plugin-HAMLog

**Typecho 业余无线电通联日志插件**

适配 Typecho 的业余无线电通联日志插件，用于管理和前台展示、查询通联日志。

[![GitHub release](https://img.shields.io/github/v/tag/bg8ixz/Typecho-Plugin-HAMLog?style=flat-square&logo=github&color=blue)](https://github.com/bg8ixz/Typecho-Plugin-HAMLog/releases)
[![GitHub stars](https://img.shields.io/github/stars/bg8ixz/Typecho-Plugin-HAMLog?style=flat-square&logo=github)](https://github.com/bg8ixz/Typecho-Plugin-HAMLog/stargazers)
[![GitHub license](https://img.shields.io/github/license/bg8ixz/Typecho-Plugin-HAMLog?style=flat-square)](https://github.com/bg8ixz/Typecho-Plugin-HAMLog/blob/main/LICENSE)
[![Typecho](https://img.shields.io/badge/Typecho-1.2%2B-brightgreen?style=flat-square)](http://typecho.org)

</div>

---

## ✨ 功能特性

- 📤 **ADIF 文件上传** - 支持 .adi/.adif 格式文件上传，完整解析所有字段
- 🛰️ **卫星通联支持** - 支持卫星通联专用字段（上行/下行频率、卫星名称等）
- 💾 **双存储模式** - 支持本地数据库和 Supabase 云端存储自由切换
- 📋 **日志管理** - 后台日志列表展示、搜索、删除、收卡/发卡状态快速切换
- 👤 **值机员信息** - 维护个人呼号、QTH、设备、天线、网格坐标等信息
- 🔍 **前台查询** - 支持呼号模糊搜索，分页展示通联日志
- 🎨 **字段自定义** - 灵活配置前后台显示字段
- 🔒 **数据安全** - 插件禁用/卸载不删除任何数据

## 📦 安装方法

1. 下载插件并解压，将 `HAMLog` 文件夹上传至 `/usr/plugins/` 目录
2. 登录 Typecho 后台，进入「控制台」→「插件」→ 找到「HAMLog」并启用
3. 插件启用时自动创建所需数据库表

## 🎯 使用指南

### 基础配置

1. **存储模式设置**
   - 默认使用本地数据库存储
   - 如需使用 Supabase，填写 API 地址、API Key 和数据表名

2. **显示字段配置**
   - 配置前台页面显示字段
   - 配置后台日志列表显示字段

### 后台功能

1. **日志上传**
   - 上传 ADIF 格式日志文件（.adi/.adif）
   - 解析预览后确认导入
   - 自动跳过重复数据（按 CALL + QSO_DATE + TIME_ON 去重）

2. **日志管理**
   - 查看通联日志列表，支持分页
   - 按呼号搜索日志
   - 快速切换收卡/发卡状态
   - 删除单条日志

3. **信息维护**
   - 设置值机员呼号、QTH、设备、天线、网格坐标
   - 信息将在前台页面展示

### 前台调用

在 Typecho 中创建独立页面，在该页面中使用短代码调用：

```
[HAMLog]
```

页面别名建议设置为 `log`，访问地址示例：`https://your-domain.com/log.html`

### ADIF 字段支持

插件支持以下 ADIF 标准字段解析：

| 字段 | 说明 | 备注 |
|:---:|:---:|:---|
| CALL | 对方呼号 | 解析后上传到数据库为 CALL_SIGN |
| QSO_DATE | 通联日期 | 核心必填字段，格式：YYYY-MM-DD |
| TIME_ON | 通联时间 | 核心必填字段，格式：HH:MM:SS |
| BAND | 频段 | 如 2M、70CM |
| BAND_RX | 接收频段 | 卫星通联 |
| MODE | 通信模式 | 如 FM、SSB、FT8 |
| FREQ | 频率 | 发射频率 |
| FREQ_RX | 接收频率 | 接收频率 |
| RST_SENT | 发送信号报告 | 如 59 |
| RST_RCVD | 接收信号报告 | 如 59 |
| TX_PWR | 己方功率 | 单位：W |
| RX_PWR | 对方功率 | 单位：W |
| QTH | 对方地址 | 对方QTH |
| GRID | 网格坐标 | 梅登海德网格坐标 |
| PROP_MODE | 传播模式 | 卫星通联填 SAT |
| SAT_NAME | 卫星名称 | 如 ARISS、ISS |
| REMARK | 备注 | 通联备注信息 |
| CARD_SEND | 已发卡片 | 插件扩展字段，0=未发卡，1=已发卡 |
| CARD_RCV | 已收卡片 | 插件扩展字段，0=未收卡，1=已收卡 |

## 🛠️ 技术栈

- **后端**: PHP 7.0+
- **数据库**: MySQL 5.6+ / Supabase
- **前端**: 原生 HTML/CSS（极简风格）
- **框架**: Typecho 1.2+

## 📝 更新日志

### v1.0.0 (2026-5-9)
- 🎉 初始版本发布
- ✨ 支持 ADIF 文件上传与解析
- 🌍 支持本地数据库模式（Supabase 存储模式待更新）
- 📋 后台日志管理功能
- 👤 值机员信息维护
- 🔍 前台日志展示与搜索
- 📱 响应式表格布局

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

## 📄 开源协议

本项目基于 [MIT License](LICENSE) 开源

## 💖 支持作者

如果这个插件对您有帮助，欢迎：

- ⭐ Star 本项目
- 🐛 提交 Bug 反馈
- 💡 提出新功能建议
- 📝 分享使用体验

## 🔗 相关链接

- [Typecho 官网](http://typecho.org)
- [问题反馈](https://github.com/bg8ixz/Typecho-Plugin-HAMLog/issues)

---

<div align="center">

Made with ❤️ by [BG8IXZ](https://qrz.com/db/bg8ixz)

</div>
