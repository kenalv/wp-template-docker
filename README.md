# WordPress Headless REST API Template

A production-ready WordPress template optimized for headless/REST API usage with Docker, Azure MySQL, and automated deployment.

## 🚀 Features

- **WordPress 6.8+ with PHP 8.3** - Latest stable versions
- **Docker Multi-Environment** - Development and production configurations
- **Azure MySQL Integration** - SSL-secured cloud database connectivity
- **Headless API Optimized** - Custom endpoints, CORS, authentication
- **CI/CD Pipeline** - Automated builds and deployments with GitHub Actions
- **Traefik Integration** - Reverse proxy with automatic SSL certificates
- **Performance Monitoring** - Built-in performance tracking and optimization
- **Security Hardened** - SSL certificates, secure configurations, environment variables

## 📦 Quick Start

### 1. Clone and Setup

```bash
# Clone the template
git clone <your-repo-url> my-wp-project
cd my-wp-project

# Copy environment template
cp .env.example .env
```

### 2. Configure Environment

Edit `.env` file with your settings:

```env
# Database Configuration
DB_HOST=your-mysql-server.mysql.database.azure.com
DB_USER=your-username
DB_PASSWORD=your-password
DB_NAME=your-database

# Project Configuration
PROJECT_NAME=my-wp-project
DOMAIN=localhost  # or your-domain.com for production
LETSENCRYPT_EMAIL=your-email@domain.com
```

### 3. Start Development

```bash
# Start development server
docker-compose up -d

# View logs
docker-compose logs -f

# Access your site
open http://localhost:8081
```

## 📁 Project Structure

```
wordpress-headless-template/
├── .github/
│   └── workflows/
│       └── docker-publish.yml      # CI/CD pipeline
├── wp-content/
│   ├── mu-plugins/                 # Must-use plugins (API core)
│   │   ├── api-cors.php           # CORS configuration
│   │   ├── api-endpoints.php      # Custom API endpoints
│   │   ├── api-auth.php           # JWT authentication
│   │   └── performance-monitor.php # Performance monitoring
│   ├── plugins/
│   │   └── custom-api/            # Custom API plugin
│   └── themes/
│       └── headless-theme/        # Minimal theme for API
├── database/
│   ├── schema.sql                 # Database schema
│   └── migrations/                # Database migrations
├── docker-compose.yml             # Development configuration
├── docker-compose.prod.yml        # Production configuration
├── Dockerfile                     # Custom WordPress image
├── .env.example                   # Environment template
├── .gitignore                     # Git ignore rules
└── README.md                      # This file
```

## 🔧 Configuration Files

### Core Files You'll Customize:

- **`.env`** - Environment variables and database credentials
- **`wp-content/mu-plugins/`** - Core API functionality
- **`wp-content/plugins/custom-api/`** - Your custom API logic
- **`database/schema.sql`** - Database structure
- **`.github/workflows/docker-publish.yml`** - CI/CD pipeline

### Files You Don't Version:

- `wp-content/uploads/` - User uploaded files
- `wp-content/cache/` - Cache files
- WordPress core files
- Third-party themes (unless customized)

## 🌐 API Endpoints

The template includes these default endpoints:

```
GET  /wp-json/wp/v2/posts          # WordPress posts
GET  /wp-json/wp/v2/pages          # WordPress pages
GET  /wp-json/custom/v1/portfolio   # Custom portfolio endpoint
GET  /wp-json/custom/v1/services    # Custom services endpoint
POST /wp-json/custom/v1/contact     # Contact form endpoint
POST /wp-json/jwt-auth/v1/token     # JWT authentication
```

## 🔒 Security Features

- SSL certificate management (Let's Encrypt)
- JWT authentication for API access
- CORS configuration for frontend integration
- Environment variable protection
- File upload restrictions
- Database connection encryption (Azure SSL)

## 🚀 Deployment

### GitHub Actions CI/CD

1. **Set up GitHub Secrets:**
   ```
   GHCR_TOKEN=<your-github-token>
   VPS_HOST=<your-server-ip>
   VPS_USER=<your-server-user>
   VPS_SSH_KEY=<your-private-ssh-key>
   ```

2. **Push to trigger deployment:**
   ```bash
   git push origin main
   ```

### Manual Deployment

```bash
# Build and push image
docker build -t your-registry/project-name:latest .
docker push your-registry/project-name:latest

# Deploy to server
docker-compose -f docker-compose.prod.yml up -d
```

## 🛠️ Development

### Adding Custom Endpoints

Create new endpoints in `wp-content/mu-plugins/api-endpoints.php`:

```php
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/portfolio', [
        'methods' => 'GET',
        'callback' => 'get_portfolio_items',
        'permission_callback' => '__return_true'
    ]);
});
```

### Database Changes

1. Create migration in `database/migrations/`
2. Update `database/schema.sql`
3. Test locally with `docker-compose restart`

### Performance Monitoring

Access performance data at:
- `http://localhost:8081/wp-admin/admin.php?page=performance-monitor`
- Check Docker logs: `docker-compose logs wordpress`

## 🐛 Troubleshooting

### Common Issues:

**Database Connection Failed:**
```bash
# Check database credentials in .env
# Verify Azure MySQL firewall rules
# Check SSL certificate
docker-compose logs wordpress
```

**API Endpoints Not Working:**
```bash
# Check WordPress permalink structure
# Verify .htaccess rules
# Check CORS configuration
curl -I http://localhost:8081/wp-json/wp/v2/posts
```

**Docker Build Fails:**
```bash
# Clear Docker cache
docker system prune -a
# Rebuild image
docker-compose build --no-cache
```

## 📝 License

This template is based on the KENTHDEV-CMS project and is available under the same license terms.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📞 Support

For issues and questions:
- Check the troubleshooting section
- Review Docker and WordPress logs
- Create an issue in the repository

---

**Built with ❤️ for headless WordPress development**
