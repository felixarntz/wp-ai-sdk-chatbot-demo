#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Get plugin info
const pluginFile = 'wp-ai-sdk-chatbot-demo.php';
const pluginContent = fs.readFileSync(pluginFile, 'utf8');
const versionMatch = pluginContent.match(/Version:\s*(.+)/);
const version = versionMatch ? versionMatch[1].trim() : '1.0.0';

const pluginName = 'wp-ai-sdk-chatbot-demo';
const zipName = `${pluginName}-v${version}.zip`;

console.log(`Creating plugin zip: ${zipName}`);

// Files and directories to include in the plugin zip
const includePatterns = [
    'wp-ai-sdk-chatbot-demo.php',
    'README.md',
    'build/',
    'includes/',
    'third-party/',
    'vendor/',
    'composer.json',
    'composer.lock'
];

// Files and directories to exclude
const excludePatterns = [
    '.git*',
    'node_modules/',
    'src/',
    'scripts/',
    'tools/',
    '.env*',
    'package*.json',
    'tsconfig.json',
    'webpack.config.js',
    'phpcs.xml.dist',
    'phpstan.neon.dist',
    'scoper.inc.php',
    '*.log',
    '.DS_Store',
    'Thumbs.db'
];

// Create temp directory
const tempDir = `${pluginName}-temp`;
const finalDir = pluginName;

try {
    // Clean up any existing temp directories
    if (fs.existsSync(tempDir)) {
        execSync(`rm -rf ${tempDir}`);
    }
    if (fs.existsSync(finalDir)) {
        execSync(`rm -rf ${finalDir}`);
    }
    if (fs.existsSync(zipName)) {
        fs.unlinkSync(zipName);
    }

    // Create plugin directory
    fs.mkdirSync(finalDir, { recursive: true });

    // Copy files using rsync for efficiency
    const includeArgs = includePatterns.map(pattern => `--include="${pattern}"`).join(' ');
    const excludeArgs = excludePatterns.map(pattern => `--exclude="${pattern}"`).join(' ');
    
    // Copy everything first, then apply excludes
    console.log('Copying plugin files...');
    execSync(`rsync -av --progress ${excludeArgs} --exclude="${tempDir}/" --exclude="${finalDir}/" . ${finalDir}/`);

    // Ensure only included patterns are kept (remove anything not explicitly included)
    console.log('Cleaning up unnecessary files...');
    
    // Remove source files that shouldn't be in production
    const cleanupPaths = [
        `${finalDir}/src`,
        `${finalDir}/scripts`, 
        `${finalDir}/tools`,
        `${finalDir}/package.json`,
        `${finalDir}/package-lock.json`,
        `${finalDir}/tsconfig.json`,
        `${finalDir}/webpack.config.js`,
        `${finalDir}/phpcs.xml.dist`,
        `${finalDir}/phpstan.neon.dist`,
        `${finalDir}/scoper.inc.php`
    ];

    cleanupPaths.forEach(cleanupPath => {
        if (fs.existsSync(cleanupPath)) {
            execSync(`rm -rf ${cleanupPath}`);
        }
    });

    // Create the zip file
    console.log('Creating zip archive...');
    execSync(`zip -r ${zipName} ${finalDir}`);

    // Clean up temp directory
    execSync(`rm -rf ${finalDir}`);

    // Get file size
    const stats = fs.statSync(zipName);
    const fileSizeInMB = (stats.size / (1024 * 1024)).toFixed(2);

    console.log(`✅ Plugin zip created successfully!`);
    console.log(`📦 File: ${zipName}`);
    console.log(`📏 Size: ${fileSizeInMB} MB`);
    console.log(`🎯 Ready for WordPress installation`);

} catch (error) {
    console.error('❌ Error creating plugin zip:', error.message);
    
    // Clean up on error
    try {
        if (fs.existsSync(tempDir)) execSync(`rm -rf ${tempDir}`);
        if (fs.existsSync(finalDir)) execSync(`rm -rf ${finalDir}`);
    } catch (cleanupError) {
        // Ignore cleanup errors
    }
    
    process.exit(1);
}