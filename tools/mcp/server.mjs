#!/usr/bin/env node

import { readFile, readdir } from 'node:fs/promises';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const docs = {
    index: 'docs/README.md',
    development: 'docs/en/development.md',
    configuration: 'docs/en/configuration.md',
    components: 'docs/en/components.md',
    architecture: 'docs/en/architecture.md',
    context: '.agents/project-context.md',
};

const tools = [
    {
        name: 'project_map',
        description: 'Return the important directories and project boundaries.',
        inputSchema: { type: 'object', properties: {}, additionalProperties: false },
    },
    {
        name: 'route_conventions',
        description: 'Return canonical route naming and legacy compatibility rules.',
        inputSchema: { type: 'object', properties: {}, additionalProperties: false },
    },
    {
        name: 'read_project_doc',
        description: 'Read one approved project document by its short name.',
        inputSchema: {
            type: 'object',
            properties: { name: { type: 'string', enum: Object.keys(docs) } },
            required: ['name'],
            additionalProperties: false,
        },
    },
    {
        name: 'find_project_files',
        description: 'Find tracked source files whose path contains a query.',
        inputSchema: {
            type: 'object',
            properties: { query: { type: 'string', minLength: 1 } },
            required: ['query'],
            additionalProperties: false,
        },
    },
    {
        name: 'translation_workflow',
        description: 'Return the project translation command and source locale rules.',
        inputSchema: { type: 'object', properties: {}, additionalProperties: false },
    },
    {
        name: 'shared_layers',
        description: 'Explain where helpers, traits, services, and support classes belong.',
        inputSchema: { type: 'object', properties: {}, additionalProperties: false },
    },
];

const projectMap = {
    core: 'Laravel 13 + Inertia/Vue application in app/, routes/, and resources/js/.',
    media: 'MediaMTX and recording worker configuration in docker-compose.media-node.yml and docker/mediamtx/.',
    storage: 'MinIO/S3 configuration is in config/filesystems.php and the environment examples.',
    docs: 'English documentation is in docs/en; the index and language map are in docs/README.md.',
    mcp: 'This local MCP is tools/mcp/server.mjs and is configured by .vscode/mcp.json.',
    sharedLayers: 'Helpers: app/Helpers/helpers.php; traits: app/Traits; services: app/Services; support: app/Support.',
};

const send = (message) => {
    const body = JSON.stringify(message);

    process.stdout.write(`Content-Length: ${Buffer.byteLength(body)}\r\n\r\n${body}`);
};

const result = (id, value) => send({
    jsonrpc: '2.0',
    id,
    result: { content: [{ type: 'text', text: typeof value === 'string' ? value : JSON.stringify(value, null, 2) }] },
});

const walk = async (directory, query, found = []) => {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
        if (['node_modules', 'vendor', 'storage', 'public/build', '.git'].includes(entry.name)) {
continue;
}

        const target = join(directory, entry.name);

        if (entry.isDirectory()) {
await walk(target, query, found);
} else if (relative(root, target).toLowerCase().includes(query.toLowerCase())) {
found.push(relative(root, target));
}
    }

    return found.slice(0, 100);
};

const handle = async (message) => {
    if (message.method === 'initialize') {
        return send({ jsonrpc: '2.0', id: message.id, result: { protocolVersion: '2024-11-05', capabilities: { tools: {} }, serverInfo: { name: 'nossa-casa-project', version: '1.0.0' } } });
    }

    if (message.method === 'notifications/initialized') {
return;
}

    if (message.method === 'tools/list') {
return send({ jsonrpc: '2.0', id: message.id, result: { tools } });
}

    if (message.method !== 'tools/call') {
return;
}

    const name = message.params?.name;
    const args = message.params?.arguments ?? {};

    if (name === 'project_map') {
return result(message.id, projectMap);
}

    if (name === 'route_conventions') {
return result(message.id, 'Canonical routes use English plural nouns: /api/events, /api/posts, /api/churches, /api/users, /dashboard/events, and /live-streams/{id}. Legacy Portuguese paths are compatibility aliases only.');
}

    if (name === 'read_project_doc') {
        const path = docs[args.name];

        if (!path) {
return result(message.id, `Unknown document: ${args.name}`);
}

        return result(message.id, await readFile(join(root, path), 'utf8'));
    }

    if (name === 'translation_workflow') {
        return result(message.id, 'English is canonical. Use resources/js/locales/en.json and lang/en/*.php for source keys, then run php artisan lang:translate or php artisan lang:translate --dynamic-only. Use useI18n().t() in Vue and __()/trans() in PHP.');
    }

    if (name === 'shared_layers') {
        return result(message.id, 'Use app/Helpers/helpers.php for global file, URL, and storage helpers; app/Traits for reusable model/controller behavior; app/Services for integrations and application workflows; and app/Support for focused domain services such as church context, terminology, embeds, mail, recording paths, and temporary URLs.');
    }

    if (name === 'find_project_files') {
return result(message.id, await walk(root, args.query));
}

    return result(message.id, `Unknown tool: ${name}`);
};

let input = Buffer.alloc(0);
process.stdin.setEncoding('utf8');
process.stdin.on('data', async (chunk) => {
    input = Buffer.concat([input, Buffer.from(chunk)]);

    while (true) {
        const separator = input.indexOf('\r\n\r\n');

        if (separator < 0) {
break;
}

        const header = input.subarray(0, separator).toString('utf8');
        const match = header.match(/Content-Length:\s*(\d+)/i);

        if (!match) {
            input = input.subarray(separator + 4);
            continue;
        }

        const length = Number(match[1]);
        const start = separator + 4;

        if (input.length < start + length) {
break;
}

        const body = input.subarray(start, start + length).toString('utf8');
        input = input.subarray(start + length);
        await handle(JSON.parse(body));
    }
});
