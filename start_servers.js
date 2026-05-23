const fs = require('fs');
const path = require('path');
const { spawn, spawnSync } = require('child_process');

const rootDir = __dirname;
const envPath = path.join(rootDir, '.env');
const artisanPath = path.join(rootDir, 'artisan');

if (!fs.existsSync(artisanPath)) {
  console.error('[bootstrap] artisan file not found in project root.');
  process.exit(1);
}

function readDotEnv(filePath) {
  if (!fs.existsSync(filePath)) {
    return {};
  }

  const values = {};
  const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);

  for (const line of lines) {
    const trimmed = line.trim();

    if (!trimmed || trimmed.startsWith('#')) {
      continue;
    }

    const separatorIndex = trimmed.indexOf('=');

    if (separatorIndex === -1) {
      continue;
    }

    const key = trimmed.slice(0, separatorIndex).trim();
    let value = trimmed.slice(separatorIndex + 1).trim();

    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    values[key] = value;
  }

  return values;
}

function getSetting(key, fallback) {
  if (process.env[key] !== undefined && process.env[key] !== '') {
    return process.env[key];
  }

  if (envValues[key] !== undefined && envValues[key] !== '') {
    return envValues[key];
  }

  return fallback;
}

function isTruthy(value, fallback = false) {
  if (value === undefined || value === null || value === '') {
    return fallback;
  }

  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase());
}

function runBootstrapCommand(name, args, required = true) {
  console.log(`[bootstrap] php ${args.join(' ')}`);

  const result = spawnSync(phpBinary, args, {
    cwd: rootDir,
    stdio: 'inherit',
    shell: false,
  });

  if (result.error) {
    console.error(`[bootstrap] ${name} failed: ${result.error.message}`);

    if (required) {
      process.exit(1);
    }

    return false;
  }

  if (result.status !== 0) {
    console.error(`[bootstrap] ${name} exited with code ${result.status}`);

    if (required) {
      process.exit(result.status || 1);
    }

    return false;
  }

  return true;
}

function attachPrefixedLogs(serviceName, child) {
  const pipe = (stream, method) => {
    if (!stream) {
      return;
    }

    let buffer = '';

    stream.on('data', (chunk) => {
      buffer += chunk.toString();
      const lines = buffer.split(/\r?\n/);
      buffer = lines.pop() || '';

      for (const line of lines) {
        if (line.trim() !== '') {
          method(`[${serviceName}] ${line}`);
        }
      }
    });

    stream.on('end', () => {
      if (buffer.trim() !== '') {
        method(`[${serviceName}] ${buffer}`);
      }
    });
  };

  pipe(child.stdout, console.log);
  pipe(child.stderr, console.error);
}

function terminateChild(child) {
  if (!child || child.exitCode !== null || child.killed) {
    return;
  }

  if (process.platform === 'win32') {
    spawn('taskkill', ['/pid', String(child.pid), '/t', '/f'], {
      stdio: 'ignore',
      shell: false,
    });
    return;
  }

  child.kill('SIGTERM');
}

function shutdown(signal) {
  if (shuttingDown) {
    return;
  }

  shuttingDown = true;
  shutdownExitCode = pendingExitCode;
  console.log(`[shutdown] ${signal} received, stopping services...`);

  for (const service of services) {
    terminateChild(service.child);
  }

  setTimeout(() => process.exit(shutdownExitCode), 500);
}

function startService(service) {
  console.log(`[start] ${service.name}: php ${service.args.join(' ')}`);

  const child = spawn(phpBinary, service.args, {
    cwd: rootDir,
    stdio: ['inherit', 'pipe', 'pipe'],
    shell: false,
  });

  attachPrefixedLogs(service.name, child);

  child.on('error', (error) => {
    console.error(`[${service.name}] failed to start: ${error.message}`);

    if (!shuttingDown) {
      pendingExitCode = 1;
      shutdown(`${service.name} startup failure`);
    }
  });

  child.on('close', (code, signal) => {
    const reason = signal ? `signal ${signal}` : `code ${code}`;
    console.log(`[${service.name}] exited with ${reason}`);

    if (!shuttingDown) {
      pendingExitCode = code || 1;
      shutdown(`${service.name} exited`);
    }
  });

  services.push({ ...service, child });
}

const envValues = readDotEnv(envPath);
const phpBinary = process.env.PHP_BINARY || 'php';
const serveHost = getSetting('ARTISAN_HOST', '127.0.0.1');
const servePort = getSetting('ARTISAN_PORT', '8000');
const queueConnection = getSetting('QUEUE_CONNECTION', 'sync');
const aiInboxEnabled = isTruthy(getSetting('AI_ORDER_SCAN_INBOX_ENABLED', 'true'), true);
const aiInboxQueueConnection = getSetting('AI_ORDER_SCAN_INBOX_QUEUE_CONNECTION', 'database_ai_inbox');
const aiInboxQueueName = getSetting('AI_ORDER_SCAN_INBOX_QUEUE_NAME', 'ai-inbox');
const broadcastDriver = getSetting('BROADCAST_DRIVER', 'log');
const services = [];
let shuttingDown = false;
let pendingExitCode = 0;
let shutdownExitCode = 0;

const bootstrapCommands = [
  {
    name: 'optimize:clear',
    args: ['artisan', 'optimize:clear'],
    required: true,
  },
  {
    name: 'queue:restart',
    args: ['artisan', 'queue:restart'],
    required: false,
  },
];

for (const command of bootstrapCommands) {
  runBootstrapCommand(command.name, command.args, command.required);
}

if (broadcastDriver === 'log' || broadcastDriver === 'null') {
  console.log(`[bootstrap] broadcast driver is "${broadcastDriver}", no separate emitter service will be started.`);
}

const runtimeServices = [
  {
    name: 'server',
    args: ['artisan', 'serve', `--host=${serveHost}`, `--port=${servePort}`],
  },
  {
    name: 'scheduler',
    args: ['artisan', 'schedule:work'],
  },
];

if (queueConnection !== 'sync' && queueConnection !== aiInboxQueueConnection) {
  runtimeServices.push({
    name: 'queue-default',
    args: ['artisan', 'queue:work', queueConnection, '--queue=default', '--tries=3', '--timeout=180', '--sleep=3'],
  });
}

if (aiInboxEnabled) {
  runtimeServices.push({
    name: 'queue-ai-inbox',
    args: [
      'artisan',
      'queue:work',
      aiInboxQueueConnection,
      `--queue=${aiInboxQueueName}`,
      '--tries=3',
      '--timeout=180',
      '--sleep=3',
    ],
  });
}

for (const service of runtimeServices) {
  startService(service);
}

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
