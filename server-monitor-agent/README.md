# Server Monitor Agent

A lightweight Node.js agent for collecting and sending server metrics to a central monitoring dashboard.

## Features

- Real-time CPU, RAM, and disk usage monitoring
- Windows service status collection and queued start/stop/restart commands
- Automatic retry on failed transmissions
- Configurable collection intervals
- API key authentication
- Cross-platform support (Windows, Linux, macOS)

## Installation

1. **Install Node.js** (version 14 or higher)

2. **Clone or download** this repository

3. **Install dependencies:**
   ```bash
   npm install
   ```

4. **Configure the agent:**
   Edit `config.json` with your settings:
   ```json
   {
     "serverId": "your-server-id",
     "apiUrl": "https://your-domain.com/api/metrics",
     "apiKey": "your-api-key",
     "intervalSeconds": 5,
     "retryAttempts": 3,
     "retryDelayMs": 1000,
     "requestTimeoutMs": 10000,
     "autoDiscoverWindowsServices": true,
     "windowsServiceDiscoveryPatterns": ["coldfusion", "cfusion", "mysql", "mariadb", "iis", "w3svc"],
     "windowsServiceDiscoveryLimit": 50,
     "windowsServices": ["Spooler", "WinDefend"]
   }
   ```

   You can also configure the agent with environment variables:
   ```bash
   SERVER_MONITOR_CONFIG=/opt/server-monitor-agent/config.json
   SERVER_MONITOR_SERVER_ID=your-server-id
   SERVER_MONITOR_API_URL=https://your-domain.com/api/metrics
   SERVER_MONITOR_API_KEY=your-api-key
   SERVER_MONITOR_INTERVAL_SECONDS=5
   SERVER_MONITOR_RETRY_ATTEMPTS=3
   SERVER_MONITOR_RETRY_DELAY_MS=1000
   SERVER_MONITOR_REQUEST_TIMEOUT_MS=10000
   SERVER_MONITOR_AUTO_DISCOVER_WINDOWS_SERVICES=true
   SERVER_MONITOR_WINDOWS_SERVICE_DISCOVERY_PATTERNS=coldfusion,cfusion,mysql,mariadb,iis,w3svc
   SERVER_MONITOR_WINDOWS_SERVICE_DISCOVERY_LIMIT=50
   SERVER_MONITOR_WINDOWS_SERVICES=Spooler,WinDefend
   ```

## Usage

### Development
```bash
npm run dev
```

### Production
```bash
npm start
```

### Background Service (Linux)
```bash
# Install PM2 globally
npm install -g pm2

# Start as background service
pm2 start agent.js --name "server-monitor-agent"

# Save PM2 configuration
pm2 save

# Set up PM2 to start on boot
pm2 startup
```

### Background Service (Windows)
```bash
# Recommended: install as an elevated scheduled task running as SYSTEM.
# Run PowerShell as Administrator:
.\install-agent-task.ps1

# Stop/start the task later:
.\stop-agent-task.ps1
.\start-agent-task.ps1

# Remove it:
.\uninstall-agent-task.ps1
```

The scheduled task runs the packaged `.exe` with service-control privileges. This is required for Start/Stop/Restart commands to affect Windows services such as MySQL, IIS, or ColdFusion.

### Replacing a Running Packaged Agent

If `npm run build:exe` creates `dist\server-monitor-agent-new.exe` because the live executable is locked, replace it from an Administrator PowerShell window:

```powershell
Stop-Process -Name server-monitor-agent -Force
Copy-Item D:\server-monitor-agent\dist\server-monitor-agent-new.exe D:\server-monitor-agent\dist\server-monitor-agent.exe -Force
.\start-agent-task.ps1
```

The web dashboard requires `module.service_control` in addition to `module.server_metrics` before a user can queue Start/Stop/Restart commands.

## Configuration

| Setting | Description | Default |
|---------|-------------|---------|
| `serverId` | Unique identifier for this server | `server-001` |
| `apiUrl` | URL of the central API endpoint | `http://localhost:8000/api/metrics` |
| `apiKey` | API key for authentication | `agent-key-123` |
| `intervalSeconds` | How often to collect metrics (seconds) | `5` |
| `retryAttempts` | Number of retry attempts on failure | `3` |
| `retryDelayMs` | Delay between retry attempts (ms) | `1000` |
| `requestTimeoutMs` | HTTP request timeout (ms) | `10000` |
| `autoDiscoverWindowsServices` | Auto-discover likely app/database/web services on Windows | `true` |
| `windowsServiceDiscoveryPatterns` | Name/display-name keywords used for auto-discovery | app/db/web service keywords |
| `windowsServiceDiscoveryLimit` | Maximum auto-discovered services sent per heartbeat | `50` |
| `windowsServices` | Windows service names to check | `[]` |

## Metrics Collected

- **CPU Usage**: Current CPU load percentage
- **RAM Usage**: Used and total RAM in GB
- **Disk Usage**: Used and total disk space in GB (main system disk)
- **Windows Services**: Status and startup type for configured service names

## Troubleshooting

### Common Issues

1. **Permission denied for system information**
   - Run with elevated privileges: `sudo node agent.js`

2. **Network connection failed**
   - Check API URL and network connectivity
   - Verify API key is correct

3. **High CPU usage**
   - Increase collection interval in config
   - Check for memory leaks

### Logs

The agent logs all activities to the console. For production, consider redirecting output to log files:

```bash
npm start > agent.log 2>&1
```

## Security

- Store API keys securely (environment variables recommended for production)
- Use HTTPS for API communication
- Regularly rotate API keys
- Monitor agent logs for suspicious activity
