pipeline {
    agent any

    options {
        timestamps()
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }

    parameters {
        choice(
            name: 'DEPLOY_PROFILE',
            choices: ['production', 'development'],
            description: 'Perfil de despliegue. production levanta frontend-prod; development levanta frontend Vite.'
        )
        booleanParam(
            name: 'RUN_BACKEND_TESTS',
            defaultValue: true,
            description: 'Ejecutar php artisan test dentro del contenedor backend antes de desplegar.'
        )
        booleanParam(
            name: 'RUN_FRONTEND_CHECKS',
            defaultValue: true,
            description: 'Ejecutar npm lint/build antes de desplegar.'
        )
    }

    environment {
        COMPOSE_PROJECT_NAME = 'siprecochabamba'
        COMPOSE_BASE = 'docker compose -f docker-compose.yml'
        COMPOSE_PROD = 'docker compose -f docker-compose.yml -f docker-compose.prod.yml'
        BACKEND_HEALTH_URL = 'http://localhost:8000/api/health'
        FRONTEND_DEV_URL = 'http://localhost:5173'
        FRONTEND_PROD_URL = 'http://localhost:4173'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Validate Tools') {
            steps {
                sh '''
                    set -eu
                    docker --version
                    docker compose version
                '''
            }
        }

        stage('Frontend Checks') {
            when {
                expression { return params.RUN_FRONTEND_CHECKS }
            }
            steps {
                sh '''
                    set -eu
                    docker run --rm \
                        -u "$(id -u):$(id -g)" \
                        -v "$PWD/frontend:/app" \
                        -w /app \
                        node:22-alpine \
                        sh -c "npm ci && npm run lint && npm run build"
                '''
            }
        }

        stage('Build Images') {
            steps {
                sh '''
                    set -eu
                    if [ "${DEPLOY_PROFILE}" = "production" ]; then
                        ${COMPOSE_PROD} build backend frontend-prod
                    else
                        ${COMPOSE_BASE} build backend frontend
                    fi
                '''
            }
        }

        stage('Start Dependencies') {
            steps {
                sh '''
                    set -eu
                    ${COMPOSE_BASE} up -d db backend
                    ${COMPOSE_BASE} ps
                '''
            }
        }

        stage('Backend Tests') {
            when {
                expression { return params.RUN_BACKEND_TESTS }
            }
            steps {
                sh '''
                    set -eu
                    ${COMPOSE_BASE} exec -T backend php artisan test
                '''
            }
        }

        stage('Deploy') {
            steps {
                sh '''
                    set -eu
                    if [ "${DEPLOY_PROFILE}" = "production" ]; then
                        ${COMPOSE_BASE} stop frontend || true
                        ${COMPOSE_PROD} up -d --build db backend frontend-prod
                    else
                        ${COMPOSE_BASE} up -d --build db backend frontend
                        ${COMPOSE_PROD} stop frontend-prod || true
                    fi
                    ${COMPOSE_BASE} ps
                '''
            }
        }

        stage('Health Check') {
            steps {
                sh '''
                    set -eu

                    for attempt in $(seq 1 30); do
                        if curl -fsS "${BACKEND_HEALTH_URL}" >/dev/null; then
                            break
                        fi
                        if [ "$attempt" -eq 30 ]; then
                            echo "Backend health check failed: ${BACKEND_HEALTH_URL}"
                            exit 1
                        fi
                        sleep 2
                    done

                    if [ "${DEPLOY_PROFILE}" = "production" ]; then
                        FRONTEND_URL="${FRONTEND_PROD_URL}"
                    else
                        FRONTEND_URL="${FRONTEND_DEV_URL}"
                    fi

                    for attempt in $(seq 1 30); do
                        if curl -fsS "$FRONTEND_URL" >/dev/null; then
                            break
                        fi
                        if [ "$attempt" -eq 30 ]; then
                            echo "Frontend health check failed: $FRONTEND_URL"
                            exit 1
                        fi
                        sleep 2
                    done
                '''
            }
        }
    }

    post {
        always {
            sh '''
                set +e
                ${COMPOSE_BASE} ps
                ${COMPOSE_BASE} logs --tail=120 backend
                if [ "${DEPLOY_PROFILE}" = "production" ]; then
                    ${COMPOSE_PROD} logs --tail=120 frontend-prod
                else
                    ${COMPOSE_BASE} logs --tail=120 frontend
                fi
            '''
        }
        success {
            echo 'Deploy completed successfully.'
        }
        failure {
            echo 'Deploy failed. Check the Jenkins stage logs and Docker Compose logs above.'
        }
    }
}
