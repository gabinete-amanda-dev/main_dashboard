import React from 'react';

export default function Hello(props) {
    return (
        <div className="bg-blue-100 p-4 rounded-lg">
            <div className="text-xl font-bold">Hello {props.fullName}!</div>
            <p className="text-gray-600">Welcome to your dashboard</p>
        </div>
    );
}