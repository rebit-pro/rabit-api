const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const ts = require('/app/node_modules/typescript');
const root = '/app';
const walk = (dir) =>
  fs
    .readdirSync(dir, { withFileTypes: true })
    .flatMap((e) => (e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)]))
    .sort();
const sourceFiles = walk(root + '/src');
const rel = (p) => 'frontend/' + path.relative(root, p).replaceAll('\\', '/');
const hash = (p) => crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const cfg = ts.readConfigFile(root + '/tsconfig.json', ts.sys.readFile).config;
const parsed = ts.parseJsonConfigFileContent(cfg, ts.sys, root);
const program = ts.createProgram(parsed.fileNames, parsed.options);
const checker = program.getTypeChecker();
const exported = (n) => n.modifiers?.some((m) => m.kind === ts.SyntaxKind.ExportKeyword);
const formats = ts.TypeFormatFlags.NoTruncation | ts.TypeFormatFlags.UseAliasDefinedOutsideCurrentScope;
const modules = [];
for (const filename of sourceFiles.filter(
  (p) => p.endsWith('.ts') && (p.includes('/modules/morefoto/') || p === root + '/src/api/auth.ts')
)) {
  const sf = program.getSourceFile(filename);
  if (!sf) throw new Error('No source ' + filename);
  const service = /(?:\/services\/[^/]+|\/[^/]*service)\.ts$/.test(filename);
  const declarations = [];
  for (const n of sf.statements) {
    if (!exported(n)) continue;
    const line = sf.getLineAndCharacterOfPosition(n.getStart(sf)).line + 1;
    if (ts.isInterfaceDeclaration(n) || ts.isTypeAliasDeclaration(n))
      declarations.push({ kind: ts.isInterfaceDeclaration(n) ? 'interface' : 'type', name: n.name.text, line, declaration: n.getText(sf) });
    else if (service && ts.isFunctionDeclaration(n) && n.name) {
      const sig = checker.getSignatureFromDeclaration(n);
      const returns =
        n.type?.getText(sf) ?? checker.typeToString(checker.getReturnTypeOfSignature(sig), n, formats).replaceAll('/app/', 'frontend/');
      declarations.push({
        kind: 'function',
        name: n.name.text,
        line,
        signature: n.name.text + '(' + n.parameters.map((p) => p.getText(sf)).join(', ') + '): ' + returns
      });
    } else if (service && ts.isClassDeclaration(n) && n.name)
      declarations.push({ kind: 'error', name: n.name.text, line, declaration: n.getText(sf) });
  }
  if (declarations.length) modules.push({ file: rel(filename), sha256: hash(filename), declarations });
}
const routes = [];
for (const name of ['MainRoutes', 'PublicRoutes']) {
  const filename = root + '/src/router/' + name + '.ts',
    sf = program.getSourceFile(filename);
  const visit = (n) => {
    if (ts.isObjectLiteralExpression(n)) {
      const properties = new Map(
        n.properties.filter(ts.isPropertyAssignment).map((p) => [p.name.getText(sf).replace(/['"]/g, ''), p.initializer])
      );
      const routePath = properties.get('path');
      if (routePath && ts.isStringLiteral(routePath)) {
        const local = routePath.text,
          full = name === 'MainRoutes' && !local.startsWith('/') ? '/cabinet/' + local : local;
        const meta = properties.get('meta')?.getText(sf) ?? '';
        const roles = meta
          .match(/staffRoles:\s*\[([^\]]+)\]/)?.[1]
          .match(/'([^']+)'/g)
          ?.map((v) => v.slice(1, -1));
        routes.push({
          path: full,
          name: properties.get('name')?.text ?? null,
          component:
            properties
              .get('component')
              ?.getText(sf)
              .match(/import\('([^']+)'\)/)?.[1] ?? null,
          redirect: properties.get('redirect')?.text ?? null,
          requiresAuth: name === 'MainRoutes' || /requiresAuth:\s*true/.test(meta),
          roles: name === 'MainRoutes' ? (roles ?? ['organizer', 'curator', 'head', 'teacher']) : null,
          mockOnly: full.startsWith('/demo/'),
          source: rel(filename),
          line: sf.getLineAndCharacterOfPosition(n.getStart(sf)).line + 1
        });
      }
    }
    ts.forEachChild(n, visit);
  };
  visit(sf);
}
const vue = sourceFiles.filter(
  (p) => p.endsWith('.vue') && (p.includes('/modules/morefoto/') || routes.some((r) => r.component?.replace('@/', root + '/src/') === p))
);
const components = vue.map((p) => ({
  file: rel(p),
  imports: [...fs.readFileSync(p, 'utf8').matchAll(/(?:from\s*|import\s*)['"]([^'"]+\.vue)['"]/g)].map((m) => m[1])
}));
const evidence = walk(root + '/e2e/steps/review').concat([root + '/e2e/features/review.feature']);
const result = {
  schemaVersion: 1,
  baseline: 'stage-20260908-r16-01',
  description:
    'Фактические декларации и маршруты фронтенда. Экспорты включают локальные вспомогательные функции; это не проект REST API или схемы БД.',
  sources: sourceFiles
    .concat(evidence)
    .sort()
    .map((p) => ({ file: rel(p), sha256: hash(p) })),
  routes,
  modules,
  components
};
process.stdout.write(JSON.stringify(result, null, 2) + '\n');
