import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import StandardScaler
from sklearn.metrics import mean_absolute_error, r2_score
import joblib
import json
import mysql.connector
from datetime import datetime
import os

def load_training_data():
    """Load training data from database"""
    # Database connection
    conn = mysql.connector.connect(
        host=os.environ.get('DB_HOST', 'localhost'),
        user=os.environ.get('DB_USER', 'root'),
        password=os.environ.get('DB_PASS', ''),
        database=os.environ.get('DB_NAME', 'ardhiyetu')
    )
    
    cursor = conn.cursor(dictionary=True)
    
    # Query training data
    query = """
    SELECT 
        features,
        target_value
    FROM ml_training_data
    WHERE verified = 1
    AND target_value IS NOT NULL
    AND JSON_LENGTH(features) > 0
    """
    
    cursor.execute(query)
    data = cursor.fetchall()
    cursor.close()
    conn.close()
    
    # Parse features
    X = []
    y = []
    
    for row in data:
        try:
            features = json.loads(row['features'])
            
            # Extract numeric features
            numeric_features = {
                'size': float(features.get('size', 0)),
                'location_score': float(features.get('location_score', 0.5)),
                'infrastructure': float(features.get('infrastructure', 0.5)),
                'market_trend': float(features.get('market_trend', 0.5)),
                'proximity_amenities': float(features.get('proximity_amenities', 0.5)),
                'soil_quality': float(features.get('soil_quality', 0.5)),
                'accessibility': float(features.get('accessibility', 0.5))
            }
            
            X.append(list(numeric_features.values()))
            y.append(float(row['target_value']))
            
        except (json.JSONDecodeError, ValueError) as e:
            print(f"Error parsing row: {e}")
            continue
    
    return np.array(X), np.array(y)

def train_model():
    """Train the valuation model"""
    print("Loading training data...")
    X, y = load_training_data()
    
    if len(X) < 100:
        print(f"Warning: Only {len(X)} data points available. Need at least 100 for good results.")
        return None
    
    print(f"Training with {len(X)} samples...")
    
    # Split data
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42
    )
    
    # Scale features
    scaler = StandardScaler()
    X_train_scaled = scaler.fit_transform(X_train)
    X_test_scaled = scaler.transform(X_test)
    
    # Train model
    model = RandomForestRegressor(
        n_estimators=100,
        max_depth=10,
        min_samples_split=5,
        min_samples_leaf=2,
        random_state=42,
        n_jobs=-1
    )
    
    model.fit(X_train_scaled, y_train)
    
    # Evaluate
    y_pred = model.predict(X_test_scaled)
    
    mae = mean_absolute_error(y_test, y_pred)
    r2 = r2_score(y_test, y_pred)
    
    print(f"Model trained successfully!")
    print(f"MAE: {mae:,.2f}")
    print(f"R² Score: {r2:.4f}")
    print(f"Feature importance: {model.feature_importances_}")
    
    # Save model and scaler
    model_dir = os.path.dirname(__file__)
    model_path = os.path.join(model_dir, 'models/land_valuation_model.pkl')
    scaler_path = os.path.join(model_dir, 'models/scaler.pkl')
    
    os.makedirs(os.path.dirname(model_path), exist_ok=True)
    
    joblib.dump(model, model_path)
    joblib.dump(scaler, scaler_path)
    
    # Save model metadata
    metadata = {
        'training_date': datetime.now().isoformat(),
        'training_samples': len(X),
        'test_samples': len(X_test),
        'mae': mae,
        'r2_score': r2,
        'feature_names': [
            'size', 'location_score', 'infrastructure', 
            'market_trend', 'proximity_amenities', 'soil_quality', 'accessibility'
        ],
        'feature_importance': model.feature_importances_.tolist()
    }
    
    metadata_path = os.path.join(model_dir, 'models/model_metadata.json')
    with open(metadata_path, 'w') as f:
        json.dump(metadata, f, indent=2)
    
    print(f"Model saved to {model_path}")
    print(f"Scaler saved to {scaler_path}")
    print(f"Metadata saved to {metadata_path}")
    
    return model

if __name__ == '__main__':
    train_model()