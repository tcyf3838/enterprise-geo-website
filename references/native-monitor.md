# 内置访问监测

默认随官网交付。使用 `assets/monitor` 的可运行 PHP/MySQL 模块，独立于 GEOInflow 原项目；不要求安装 Docker、WSL、PostgreSQL 或 Redis。模块复制到项目后与官网一起部署，不读取 Skill 原目录。后台统一使用网站管理员登录。

## 功能与口径

| 功能 | 已提供实现与边界 |
|---|---|
| 请求记录 | PHP 入口的匿名 GET/HEAD、路径、实际响应码、耗时、时间、来源与预览/正式范围；钩子在应用缓存之前注册 |
| 爬虫识别 | 按下方主流平台清单维护：经一手文档确认的请求 token 才进入 AI 分类；训练、搜索、用户触发与浏览器代理分列，多身份冲突及其他自动访问单列 |
| 来源核验 | OpenAI、Anthropic 爬虫、Perplexity、Mistral 用户与索引请求、Google 托管代理按各自官方清单比对 IPv4/IPv6；缺清单、缺 IP、失效、历史缺证据及资料无效分开 |
| 页面覆盖 | 当前发布目录中，严格 GET 200 的 AI 声明及官方 IP 匹配分别计数；HEAD/304 不算成功内容抓取；删除页移出当前分母，历史记录保留 |
| 趋势与异常 | 浏览趋势按北京时间日期汇总；AI 声明、官方 IP 匹配与异常响应分项统计，异常路径按状态码汇总 |
| 明细与导出 | 时间范围、数据范围、请求来源、爬虫及核验状态筛选；全部请求明细每页 20 条；CSV 最多 5000 条，按同一筛选导出，做公式转义 |
| 浏览事件 | PV、按北京时间日期轮换的匿名网络与 UA 哈希估算访客日、AI 引荐、电话/邮箱/下载点击；不是精确人数、有效询盘或成交 |
| 日志导入 | 私有目录 JSONL、10 MB 上限、带时区时间、稳定键去重、整批事务；每个来源独立查看，避免源站和边缘重复相加 |
| 维护 | 官方清单更新、失败保留上次有效快照、原文哈希归档、清理超过 90 天数据、目录导出、数据库迁移、采集状态 |

UA 可以伪造，IP 匹配仅说明来源落在该供应商公布的范围内。用途按 token 的官方说明分类，不证明某篇内容被训练、收录、引用或推荐。Googlebot、bingbot、Baiduspider、Bytespider 只显示各自标识，不能直接改名成 Gemini、Copilot、文心或豆包；Google-Extended 不是请求 UA。新平台规则必须有来源，未知继续显示未知。

浏览事件依赖脚本和来源头，可能被拦截或伪造。没有来源头时显示“直接访问 / 来源缺失”。归类按本次页面的 referrer；站内浏览不继承首访渠道，不称完整会话归因。多天 UV 为访客日，同网络/UA 可能合并，换网络可能拆分；不构建跨日个人档案。DNT/GPC、已识别机器人及真实登录管理员不进入事件统计；仅存在管理 Cookie 不能等同已登录。

默认不包含录屏、热力图、跨站多团队、地理定位数据库、自动 WAF 控制、订单/收入归因、AI 回答排名或引用检测。这些不能以空菜单、虚拟数字或虚构成功状态充数。

## 主流 AI 覆盖与统计定义

以 `assets/monitor/rules.json` 为唯一平台、请求身份与引荐域名配置，维护国内外主流助手及项目指定平台。清单是覆盖目标，不是所有平台都能确认爬虫来源的承诺；每次交付核对平台、别名、官方说明、产品域名和可用验证方法，发现新平台继续补充。当前资料核对日期为 2026-11-14。

| 平台 | 请求识别范围 |
|---|---|
| 豆包 | Bytespider 只作厂商声明，不能确认豆包产品访问。 |
| DeepSeek | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| 千问 | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| Kimi | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| 元宝 | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| 文心 | Baiduspider 仅代表百度搜索声明，不能确认文心产品访问。 |
| 智谱清言 | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| 讯飞星火 | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| 腾讯混元 | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| 纳米 AI | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| 秘塔 AI | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| MiniMax | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |
| ChatGPT / OpenAI | 三类身份分别使用对应官方地址清单；GPTBot 属于 OpenAI 训练抓取。 |
| Claude | 三类爬虫支持官方 IP 核验；Claude-Agent 浏览器单列，暂不作已核验。 |
| Gemini | 仅识别 Notebook 请求声明；Googlebot、Google-Agent 不直接归入 Gemini，Google-Extended 不是请求 UA。 |
| Copilot | bingbot 仅代表 Bing 搜索声明，不能确认 Copilot 产品访问。 |
| Perplexity | 搜索与用户触发访问分别使用对应官方地址清单。 |
| Grok | 未确认可接入的专用爬虫核验资料；X 普通来源不计为 Grok。 |
| Meta AI | 官方爬虫文档本次访问受限，身份规则待核对；Facebook / Instagram 普通流量不计为 Meta AI。 |
| Mistral | 用户触发与索引抓取支持官方 IP 核验，训练请求暂只识别声明。 |
| You.com | 官方 bot 页面转到登录，专用身份待核对；不根据第三方清单启用 YouBot。 |
| Manus | 未接入可核验的专用请求身份；来源浏览可按明确域名识别，不能由通用爬虫推算。 |

平台首页只能支持产品域名与引荐归类，不能用来证明请求身份。当前尚未取得专用官方核验依据的平台，保留覆盖项和待核实状态；不把“暂未找到依据”写成“厂商没有爬虫”。Meta 文档访问受限、You.com bot 页面需登录等具体缺口见 rules.json。厂商社区投稿和第三方 bot 列表可作线索，不能直接启用已核验统计。

**请求分类**：经官方文档确认用途的 token 才归为 AI 请求。`training` 是训练资料抓取声明，`search` 是搜索抓取声明，`user` 是用户触发取页，`agent` 是托管浏览器代理；四类分列，可以汇总为“AI 请求次数”，不可一概称为搜索爬虫。Google-Agent 只归 Google 托管代理；Gemini Notebook 只代表其指定资料取页，不能推断 Gemini 所有访问。Claude-Agent 需单独适配官方 SAIL，不能套用 Claude 三类爬虫清单。未接入验证方式的 token 仅计声明。

**来源核验**：完整 token 匹配后，使用该 token 对应的官方清单核对可信来源 IP。只匹配 UA、只落在厂商/云服务网段、第三方“AI bot”标签或伪造代理头均不足以通过。清单 URL、时间、哈希或 CIDR 无效时标资料无效；超过 7 天、缺 IP、无清单、多身份冲突及缺历史证据均不进入已核验数。核验失败是未确认，不直接断言恶意。IP 符合清单也不是加密身份认证。

**计数**：同一时间、范围与日志来源下，AI 声明数 = 经识别的四类 AI 请求数；已核验数 = 其中 `official_ip` 请求数；待核实数 = 二者之差。各用途分别展示；覆盖页数按当前目录内不同路径、GET 200 去重，HEAD、304、错误响应、控制标记、普通搜索及不明自动访问均不增加成功覆盖。GET 200 只是内容响应成功，不能保证正文完整、已被理解或引用。一次请求只能有一个身份结论，同一平台的别名不重复相加。

**引荐浏览**：只对浏览器 pageview 的 HTTP(S) 来源精确匹配已核对产品主机名。千问/通义等别名归到同一平台；旧记录原标签保留并纳入总量，不重写历史。Bing、百度、Google、微信、腾讯、Facebook、X 等通用站点不能整体当 AI 来源；不根据域名片段、UTM 自报或同厂商关系推定。App、省略来源头或跨端跳转可能无法归因。来源浏览属于可观察归类，并非身份核验或完整获客量。

平台无专用识别规则时显示“暂无法确认”；有规则且当前筛选未见记录时显示“当前范围未记录到”；采集异常单独提示。缺识别能力、缺日志、零记录不能混成一个数值 0。记录身份规则版本及所用 IP 快照哈希，留存当期规则文件；规则改变不回写历史计数，需重新分析时以独立结果说明条件。

## 面向日常使用者的报表

默认进入“数据概览”，优先回答四个问题：网页被浏览多少次、有多少已核验的 AI 来访、从 AI 平台链接带来多少浏览、发生多少联系或下载点击。四项分别来自浏览器 pageview、来源核验通过的已知 AI 请求、已识别 AI 来源的 pageview、实际采集的联系或下载事件。明确单位为次数，不把重复浏览称为人数，不将点击称为咨询成功。来源未核实的 AI 声明另列，不加进已核验指标；其他日志来源也不与应用请求重复相加。

概览每项用一句普通中文说明，配有限的每日浏览变化、热门页面和访问问题入口。先说明本机预览还是正式数据；本机数据含测试，不能据此判断获客。用条件化短句解释已经观察到什么和可以进一步查看什么，不从零记录推断无人访问、没有 AI 访问或采集一切正常。

详细分区采用“AI 来访、页面抓取、访客与点击、技术详情”等易懂名称。页面表优先显示标题，网址作为次要说明；失败响应显示“页面不存在”等含义，状态码作补充。AI 到访、成功 GET 200 抓取、从 AI 链接带来的用户浏览、后续点击分别解释，均不等于收录、引用、询盘或成交。来源核验的完整状态仍可查看，不能为通俗而抹掉证据差异。

日常概览仅提供时间和预览 / 正式范围；请求来源、机器人名称、核验状态放在明细视图的“更多筛选”。从明细返回概览时清除不适用条件，并说明概览采用网站应用记录；明细之间保留适用筛选。原始方法、状态码、IP 资料、请求总量和访客日放在折叠明细或技术详情中，保留排查、分页及导出能力。空态说明当前条件和观察范围，不填样例数据凑图表。

## 文件与安装

构建工具需要 Python 3.10+；运行需要 PHP 8.3+、PDO MySQL 和官网已有的会话/扩展。官方清单更新可选 PHP CLI + OpenSSL HTTPS，或维护电脑 Python 3.10+。网站不依赖 Python 运行。

```text
项目/
  app/monitor/           # Monitor.php、report.php、cli.php、schema.sql、rules.json 等
  app/monitor-adapter.php
  config/monitor.json    # 私有随机密钥、可信代理；不能放 public
  public/assets/monitor.js
  private/monitor/       # ranges.json、更新回执和原文快照
  private/imports/       # 授权日志；不公开
```

在 Skill 目录执行，先查看，再应用：

```sh
python scripts/install_monitor.py --project /absolute/site
python scripts/install_monitor.py --project /absolute/site --apply
```

安装工具仅负责复制通用模块并记录 SHA-256 清单；遇到项目人工修改会停止覆盖。**建站任务必须继续完成下面的宿主接线，不能把复制成功当作完成。** 更新前先备份，对差异做合并；只可覆盖与上次清单一致的文件。模块是本 Skill 自有实现，没有复用 GEOInflow 代码；不要声称是 GEOInflow 官方 PHP 版本。

## 宿主接线契约

1. 生成 `config/monitor.json`：secret 为 `bin2hex(random_bytes(32))` 的结果；可信代理默认空数组；采用 config.example.json 的结构，示例字符串不得直接用于运行。密钥不打印、不提交到公开包。origin 与 preview 从网站真实环境配置取得，不使用请求 Host 推导。
2. 生成适配器：加载宿主 PDO 和发布服务，将 `$monitor` 设为 EWMonitor 实例；初始化失败写私有错误日志并返回 null。`config` 必须有 secret、origin（例如正式 HTTPS 域名，不含路径或末尾斜线）、preview 布尔、trusted_proxies 数组和 client_ip_header。`catalog` 是同源规范路径到标题的映射；只含当前可访问页面，预览目录与正式范围不得混淆。用同源发布服务更新目录，不复制一份容易失效的业务数据。`rules` 来自项目内 rules.json，`ranges` 来自私有 ranges.json，尚无清单时可用 `['feeds'=>[]]`。
3. 迁移：用具备建表权限的维护连接执行 `cli.php migrate`：创建缺失表，并为已有监测表补充缺失的 `rule_version` 字段；历史行留空表示未记录，不补写当前版本。不重建网站业务表。`cli.php migrate` 默认复用宿主连接，也支持 `EW_MONITOR_DSN/USER/PASSWORD` 环境变量使用专门迁移账号。生产 PHP 账号保留必要 SELECT/INSERT/UPDATE/DELETE 即可，不长期授予 DDL。
4. 在公开入口加载适配器。`/monitor/token` 和 `/monitor/event` 交给 `$monitor->endpoint($path,$_SERVER,$isLoggedAdmin)`；其余公开请求在任何 PHP 缓存提前返回之前调用 `$monitor->attach($_SERVER,$isLoggedAdmin)`。第三参数/第二参数是真实管理员登录状态，不是仅凭 Cookie 存在。主入口处理异常不能被监测异常打断；缺表/断库不能伪装成正常空报表。
5. 将 tracker.js 复制到 public/assets/monitor.js，在公开 HTML 加同域 defer script；不放进后台。资料下载入口使用 `data-monitor="download_brief"`，电话和邮箱由 tel/mailto 链接捕获。没有真实联系方式时不生成虚假入口。新增表单事件必须由真实后端提交成功触发并独立验证，不能把按钮点击当提交成功。
6. 现有后台菜单增加一个“访问监测”，登录后包含 report.php，确保 `$monitor` 可用。CSV 导出需要在任何 HTML 输出前包含 report.php，**同样先鉴权**。后台响应 private,no-store，原站权限失效时禁止读取报表。模板使用 panel、filters、stats、table-wrap 等宿主基础类；按实际主题适配，表格在窄屏容器内滚动，不撑宽整页。
7. 官方清单更新、导入和清理放在受保护 CLI；不把运维原始 JSON/密钥表单暴露给运营。连接可信代理前核对其真实来源网段及覆盖头行为；默认不读取 X-Forwarded-For。例如反向代理必须覆盖 X-Real-IP，并仅信任代理的实际 IP/CIDR，不能信任所有公网来源。

最小入口形态（函数名按项目真实实现替换）：

```php
require PROJECT_ROOT.'/app/monitor-adapter.php';
$path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$isLoggedAdmin=hasAdminCookie() && authenticatedAdmin();
if(in_array($path, ['/monitor/token','/monitor/event'], true)) {
    if(!$monitor) { http_response_code(503); exit; }
    $monitor->endpoint($path, $_SERVER, $isLoggedAdmin);
}
if($monitor) $monitor->attach($_SERVER, $isLoggedAdmin);
// 然后进入宿主路由、缓存读取和页面输出。
```

token 绑定路径、签发时的匿名访客和时间，30 分钟失效；跨北京时间午夜时仍按签发日期验证，事件按实际接收日计入访客日。事件要求同源、白名单名称、事件 ID、4 KB 上限和每访客每分钟 60 条上限。限流是小型企业站的基础防滥用措施，不能称为完整抗 DDoS。代理/WAF 的边缘限流按服务器真实条件配置。公开采集端不可持有管理员会话权限。

采集接口对无效 JSON、字段类型、来源或签名返回 400；请求方法、大小和限流分别返回 405、413、429。数据库和其他服务异常返回 503，不冒充客户端输入错误或采集成功。服务异常记录接口名称、异常类型和脱敏错误码，PHP 错误日志配置在公开目录之外；响应与日志不包含请求正文、令牌、数据库连接信息或原始异常消息。故障不会阻断官网正文，维护人员按私有日志排查。

## 官方清单与数据维护

PHP CLI 每日更新（使用操作系统有效 CA；禁止关闭 TLS 校验）：

```sh
php app/monitor/update_ranges.php private/monitor/ranges.json
php app/monitor/cli.php prune
php app/monitor/cli.php catalog
```

`catalog` 输出当前适配器目录的顶层数组，每项包含同源绝对 `url` 与 `title`，可保存到私有 JSON 文件后交给 `audit_site.py` 检查。它不推断业务类型或日期；对外使用的完整 `public-catalog.json` 仍由网站发布服务按 content-model.md 生成，调用方需要额外字段时从同源业务数据提供。

Python 备用更新（与 PHP 更新任务选择一种执行，同一快照不并发更新）：

```sh
python app/monitor/update_ranges.py private/monitor/ranges.json
```

PHP 与 Python 更新器都从各自脚本同目录的 rules.json 读取 feeds，官方清单地址只维护这一处。该文件属于经维护人员核对的一手来源配置，不接受访客输入或公开接口修改。联网前完整校验清单键名和地址，只允许 HTTPS 域名地址，不接受 IP 字面量、凭据、端口、查询参数、片段或空白控制字符；配置无效时停止，不发请求或覆盖现有快照。新增平台或变更地址时同时核对请求 token 与 feed 的对应和官方依据，并更新身份规则版本。

更新器不跟随重定向；检查 JSON、CIDR 和响应大小，获取失败保留上次有效数据。原始文件放 range-history，哈希与获取时间写入快照；每条请求保留身份规则版本，核验请求保留所用 IP 快照哈希；部署和备份时一并保留当期 rules.json。更新回执 range-update-status.json 分列失败源。当前清单不倒推历史：比获取时间早超过 5 分钟的导入记录标“缺历史核验证据”；清单超过 7 天标过期，不继续作官方匹配。当前实现不自动选择历史快照重新归因。

使用 cron.example 按目标路径生成服务器任务，在授权目标安装并核对运行回执。不能只有 crontab 文件就声称已调度。本机无定时任务时明确为手动维护，保留清单时效提示。清理只删除 ew_requests/ew_events 超过 90 天记录；备份、原始导入日志和快照另按企业保留策略维护。备份官网数据库（含 ew_ 表）、私有配置和规则快照，恢复先在隔离目标验证，不覆盖现网。

PHP 本机预览不等于已部署公网。正式发布时核对 origin、preview=false、HTTPS、会话 Cookie、代理、任务调度、实际目录及 CDN；新正式环境生成自己的密钥，或按迁移计划安全保留，不将预览记录改成生产记录。

## 日志导入

将授权源站/CDN 日志转换为一行一个 JSON 对象，放入 private/imports。时间必须含 Z 或时区偏移；仅支持保留期内的 GET/HEAD，私有路径、静态资源和后台路径默认排除。稳定 key 应来自原日志文件标识与行号或供应商请求 ID，不能每次导入重生成随机值。同一观察来源沿用 source，重复导入不累加。不同来源单独查看，不默认合并为全站去重访问量。

```json
{"key":"edge-file-id:line-123","time":"2026-11-12T10:00:00+08:00","path":"/products/example","method":"GET","status":200,"ua":"original user agent","ip":"actual client IP","duration_ms":48}
```

```sh
php app/monitor/cli.php import private/imports/access.jsonl import_edge production
```

示例不能作为企业真实日志导入。命令返回新增与重复/排除数量，单行错误整批回滚；超过 10 MB 的文件拆成稳定批次。原始 IP、UA 只参与分类，不存入数据库；查询参数不落库。导入原文件仍可能包含个人信息，由维护人员在私有目录按企业策略清理。报表写清来源、时间和覆盖缺口；代理前的 403、CDN 命中及静态文件未经过 PHP 时，本地无记录不说明没有访问。

## 验收

规则维护先运行 `php scripts/test_monitor.php --rules-only`，需要 PDO SQLite，仅在内存中验证平台域名、误报排除、官方证据缺失、用途分列与汇总口径、北京时间访客日和跨午夜 token，不连接网站。项目接入后运行 `php scripts/test_monitor.php /absolute/site`（位于 Skill 目录），PDO 驱动按运行环境启用。此脚本验证 CIDR、伪造 UA、历史/过期/缺 IP、身份冲突、可信代理、来源域名、隐私字段、去重、GET 200 和无效时间；数据库测试在事务内回滚。它不能代替 HTTP 与浏览器验收。

项目生成 HTTP 集成检查：真实匿名请求与 404/HEAD 入库、token 与签名、事件回读及去重、同源/限流/大小、DNT/GPC、未登录报表/CSV 隔离、管理员排除、预览/正式隔离、过滤和导出、导入重复与错误回滚。核对无效 JSON 和字段返回 400；在隔离环境模拟数据库查询或写入故障，确认接口返回 503、私有日志可定位且没有敏感信息，官网正文仍可访问。测试前登记独立库或可精确识别的来源、请求/事件 ID、路径及访客；优先事务回滚，HTTP 测试优先独立实例。finally 仅清理登记且已获授权的对象，不得执行无范围 DELETE/TRUNCATE，也不得因当前为本机站就推定所有记录均可删除。缺少精确归属或删除权限时保留记录并说明范围，不扩大删除以追求统计归零；正式范围测试不得直接切换正在使用的站点。保存通过与失败项，不能仅保留总数。

按上面的日常报表结构组织独立视图，概览展示摘要、有限趋势和详细入口。爬虫、覆盖、异常、访客来源及事件表分页，导出注明上限；不把所有明细默认铺在同一页。切换视图保留适用的范围与时间；请求筛选只在适用的明细之间保留，改变条件重置分页，页码越界回到有效范围。访客视图仅提供适用的范围和时间，不摆出不生效的爬虫筛选。技术说明按需展开，重要的指标含义与观察边界仍直接可见；采集状态在技术详情中独立可达，不被隐藏成无反馈的故障。

指标验收用事务回滚或隔离数据逐项对账：普通浏览不能算 AI 来访，AI 请求不能算浏览器浏览，AI 来源的下载点击不能算 AI 来源浏览；来源待核实与已核验分开，HEAD / 304 不增加成功抓取页数。实际检查空数据、筛选保留与清除、同条件导出、无脚本可读以及预览 / 正式隔离。不得把测试数据留在正式统计中。

规则专测应覆盖所有配置身份和平台域名，包括仿冒域名、未知子域、通用厂商来源、伪造 UA/代理头、错误供应商清单、无效/过期快照、纯 IP 命中、训练与搜索区分、控制 token、同一请求多身份及无法确认平台的空态。更新器另在隔离副本中用受控响应检查：仅修改 rules.json 的 feeds 即作用于两种更新器，无效配置在联网前被拒绝，获取失败保留有效快照；不为测试修改正式规则或访问未核实的地址。规则专测通过不等于 MySQL 迁移、HTTP 接线或真实到访已验证。

实际浏览器检查：桌面、768px 中间宽度和约 390px 手机下进入后台，查看各视图、摘要与空状态；切换范围、提交更多筛选、分页、返回分区和导出，核对条件与数据一致。展开菜单后选中栏目、刷新、切换组，不能全部组自动展开。手机默认折叠高级筛选与说明；简单统计表完整适配宽度，宽明细表只在容器内横滑并提供提示及键盘焦点，页面不横溢。记录概览实际高度，确认常用数据不需穿过多张明细表才能到达。无真实 AI 访问时空表应该说明观察边界。用匿名浏览器真实页面事件证明脚本工作，后台已登录的页面浏览不计访客。

分别记录：模块/表/采集/事件/报表/身份规则的测试状态、官方更新回执、定时维护实际状态、浏览器检查、观察到的真实数据与部署目标。模拟 IP 测试只能证明逻辑，不能证明 OpenAI 真的访问了本机。

## 规则来源

- [OpenAI crawlers](https://developers.openai.com/api/docs/bots)：GPTBot、OAI-SearchBot、ChatGPT-User 与三份官方地址清单。
- [Anthropic crawler guidance](https://support.claude.com/en/articles/8896518-does-anthropic-crawl-data-from-the-web-and-how-can-site-owners-block-the-crawler) 与 [官方 IP 清单](https://claude.com/crawling/bots.json)。
- [Mistral crawlers](https://docs.mistral.ai/robots)：用户触发、索引、训练用途及前两者的地址清单。
- [Google 请求身份](https://developers.google.com/crawling/docs/crawlers-fetchers/google-user-triggered-fetchers)及[控制标记说明](https://developers.google.com/crawling/docs/crawlers-fetchers/google-common-crawlers)：Google-Agent、Gemini Notebook 与 Google-Extended 的区别。
- [Claude 托管浏览器身份](https://support.claude.com/en/articles/18234133)：Claude-Agent 与爬虫分开处理。
- [Perplexity crawlers](https://docs.perplexity.ai/docs/resources/perplexity-crawlers)：PerplexityBot 与 Perplexity-User 的用途与清单。

规则与网络清单会变化，变更后重新核对来源和测试；抓取许可由 robots/访问策略独立控制，本模块不自动修改它们。
