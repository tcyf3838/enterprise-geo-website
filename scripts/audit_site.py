"""Read-only same-origin HTTP checks. Does not prove facts, indexing or AI citations."""
import argparse
from html.parser import HTMLParser
import json
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request

class Page(HTMLParser):
    void = {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}
    ignored = {"head", "script", "style", "template", "nav", "footer", "svg"}
    def __init__(self):
        super().__init__()
        self.title = ""; self.h1 = 0; self.stack = []
        self.text = []; self.main_text = []; self.article_text = []
        self.has_main = False; self.has_article = False
        self.canonical = []; self.schema = 0
    def inside(self, tag):
        return any(t == tag for t, _ in self.stack)
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        blocked = (bool(self.stack and self.stack[-1][1]) or tag in self.ignored or
                   "hidden" in a or (a.get("aria-hidden") or "").lower() == "true" or
                   (tag == "header" and not (self.inside("main") or self.inside("article"))))
        if not blocked and self.inside("body"):
            if tag == "h1": self.h1 += 1
            if tag == "main": self.has_main = True
            if tag == "article": self.has_article = True
        if tag == "script" and (a.get("type") or "").lower() == "application/ld+json": self.schema += 1
        if tag == "link" and "canonical" in (a.get("rel") or "").lower().split(): self.canonical.append(a.get("href"))
        if tag not in self.void: self.stack.append((tag, blocked))
    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in self.void: self.handle_endtag(tag)
    def handle_endtag(self, tag):
        for i in range(len(self.stack)-1, -1, -1):
            if self.stack[i][0] == tag:
                del self.stack[i:]
                break
    def handle_data(self, data):
        if self.inside("title"): self.title += data
        if self.inside("body") and not self.stack[-1][1]:
            self.text.append(data.strip())
            if self.inside("main"): self.main_text.append(data.strip())
            if self.inside("article"): self.article_text.append(data.strip())
    def content(self):
        if self.has_main: return "main", self.main_text
        if self.has_article: return "article", self.article_text
        return "body", self.text

class SameOrigin(urllib.request.HTTPRedirectHandler):
    def __init__(self, origin): self.origin = origin
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if urllib.parse.urlsplit(newurl)[:2] != self.origin:
            raise ValueError("拒绝站外重定向")
        return super().redirect_request(req, fp, code, msg, headers, newurl)

def audit(catalog, base):
    origin = urllib.parse.urlsplit(base)[:2]
    if origin[0] not in ("http", "https") or not origin[1]: raise ValueError("base-url 必须是 HTTP(S) 站点")
    if not isinstance(catalog, list) or len(catalog) > 10000: raise ValueError("目录应为不超过 10000 项的数组")
    opener = urllib.request.build_opener(SameOrigin(origin))
    results = []
    for item in catalog:
        if not isinstance(item, dict) or not isinstance(item.get("url"), str): raise ValueError("目录条目需要字符串 url")
        url = item.get("url", "")
        if urllib.parse.urlsplit(url)[:2] != origin: raise ValueError("目录包含站外网址：" + url)
        row = {"url": url}
        try:
            req = urllib.request.Request(url, headers={"User-Agent": "EnterpriseGeoSkillAudit (local validation)"})
            with opener.open(req, timeout=20) as response:
                final_url = response.geturl()
                row.update(status=response.status, final_url=final_url,
                           redirected=urllib.parse.urldefrag(url)[0] != urllib.parse.urldefrag(final_url)[0],
                           content_type=response.headers.get_content_type())
                if row["content_type"] != "text/html": raise ValueError("响应不是 HTML 页面")
                raw = response.read(4*1024*1024 + 1)
                if len(raw) > 4*1024*1024: raise ValueError("响应超过检查上限")
                page = Page(); page.feed(raw.decode(response.headers.get_content_charset() or "utf-8", errors="replace")); page.close()
                region, content = page.content()
                row.update(title=page.title.strip(), h1=page.h1, text_region=region,
                           text_length=len("".join(content)), canonical=page.canonical, jsonld_blocks=page.schema)
                issues = []
                if response.status != 200: issues.append("未返回 200")
                if row["redirected"]: issues.append("目录页发生跳转，需核对规范网址")
                if not page.title.strip(): issues.append("缺少标题")
                if page.h1 != 1: issues.append("正文 H1 数量不是 1")
                if row["text_length"] < 30: issues.append("正文文本不足，需检查是否为空页")
                row.update(passed=not issues, issues=issues)
        except urllib.error.HTTPError as exc:
            row.update(passed=False, status=exc.code, final_url=exc.geturl(), error=str(exc))
        except (OSError, ValueError, LookupError, urllib.error.URLError) as exc:
            row.update(passed=False, error=str(exc))
        results.append(row)
    return {"scope": "公开目录直接返回HTML、标题、H1及正文文本粗检；不验证事实、CSS可见性、结构化内容、视觉或AI引用",
            "total": len(results), "passed": sum(r["passed"] for r in results), "pages": results}

if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("--catalog", required=True, type=Path)
    p.add_argument("--base-url", required=True)
    p.add_argument("--output", required=True, type=Path)
    a = p.parse_args()
    try:
        result = audit(json.loads(a.catalog.read_text(encoding="utf-8-sig")), a.base_url)
        a.output.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
        print(json.dumps({"passed": result["passed"], "total": result["total"]}))
        raise SystemExit(0 if result["passed"] == result["total"] and result["total"] > 0 else 1)
    except (ValueError, OSError) as exc:
        p.exit(1, str(exc)+"\n")
